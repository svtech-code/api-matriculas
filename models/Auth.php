<?php

    namespace Models;

    use Models\Connection;
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Firebase\JWT\ExpiredException;
    use Exception;
    use Flight;
    use PDO;

    class Auth extends Connection {
        
        public function __construct() {
            parent::__construct();
        }

        // método para verificar cuenta de usuario y fevolver token de sesion
        public function auth() {
            $user = Flight::request()->data->email;
            $password = Flight::request()->data->password;

            $statementEmail = $this->preConsult(
                "SELECT u.id_cuenta_usuario, u.nombre_cuenta_usuario, u.clave_cuenta_usuario, u.id_privilegio,
                SPLIT_PART(f.nombres_funcionario, ' ', 1) || ' ' || f.apellido_paterno_funcionario
                || ' ' || f.apellido_materno_funcionario AS nombres_funcionario
                FROM libromatricula.cuenta_usuario AS u
                INNER JOIN libromatricula.registro_funcionario AS f ON f.id_funcionario = u.id_funcionario
                WHERE nombre_cuenta_usuario = ?;"
            );

            try {
                $statementEmail->execute([$user]);
                if ($statementEmail->rowCount() === 1) {
                    $userAccount = $statementEmail->fetch(PDO::FETCH_OBJ);

                    if (md5($password) !== $userAccount->clave_cuenta_usuario) {
                        throw new Exception("La contraseña ingresada es incorrecta", 401);
                    }
                    
                    $now = strtotime("now");
                    $key = $_ENV['JWT_KEY'];
                    $payload = [
                        'exp' => $now + 21600,
                        // 'exp' => $now + 10,
                        'id_usuario' => $userAccount->id_cuenta_usuario,
                        'id_privilegio' => $userAccount->id_privilegio
                    ];
                    
                    $jwt = JWT::encode($payload, $key, 'HS256');
                    $this->array = [
                        "token" => $jwt,
                        "privilege" => $userAccount->id_privilegio,
                        "userName" => $userAccount->nombres_funcionario,
                    ];
                    return Flight::json($this->array);
                }

                throw new Exception("El e-mail ingresado no tiene cuenta de usuario", 406);
                
            } catch (Exception $error) {
                $statusCode = $error->getCode() ? $error->getCode() : 404;

                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                    "statusText" => "errorCode ". $statusCode,
                ]));

            } finally {
                $this->closeConnection();
            }
        }

        // método para obtener el token de una peticion a la api
        protected function getToken() {
            $headers = apache_request_headers();
            if (!isset($headers['Authorization'])) {
                throw new Exception("Autorización denegada !", 401);
            };

            $authorization = $headers['Authorization'];
            $authorizationArray = explode(" ", $authorization);
            $token = $authorizationArray[1];
            $key = $_ENV['JWT_KEY'];

            try {
                return JWT::decode($token, new Key($key, 'HS256'));

            } catch (ExpiredException $expiredException) {
                Flight::halt(401, json_encode([
                    "message" => "Error: ". $expiredException->getMessage(),
                ]));

            } catch (Exception $error) {
                $statusCode = $error->getCode() ? $error->getCode() : 404;

                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                ]));
            };
        }

        // método para validar token de sesión
        protected function validateToken() {
            $infoToken = $this->getToken();
            $query = $this->preConsult(
                "SELECT u.id_privilegio 
                FROM libromatricula.cuenta_usuario AS u 
                WHERE u.id_cuenta_usuario = ?"
            );
            try {
                $query->execute([$infoToken->id_usuario]);
                if ($query->rowCount() !== 1) {                    
                    throw new Exception("Usuario no valido !", 401);
                }

            } catch (Exception $error) {
                $statusCode = $error->getCode() ? $error->getCode(): 404;
                
                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                ]));
            } 
        }

        // método para validar privilegios
        // espera un array de enteros como paramtro
        protected function validatePrivilege($necessaryPrivilege) {
            $privilege = $this->getToken();

            try {
                if (!in_array($privilege->id_privilegio, $necessaryPrivilege)) {
                    throw new Exception("Acceso denegado por privilegios !", 403);
                }

            } catch (Exception $error) {
                $statusCode = $error->getCode() ? $error->getCode() : 404;

                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                ]));
            }    
        }

        // metodo para validar la sesion
        // permite verificar el token, sin tener que acceder al metodo protegido
        public function validateSession() {
            try {
                $this->validateToken();

            } catch (Exception $error) {
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                ]));

            } finally {
                $this->closeConnection();
            }
        }
    }

?>