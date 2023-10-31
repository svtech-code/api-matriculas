<?php

    namespace Models;

    use Models\Connection;
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Exception;
    use Flight;
    use PDO;

    class Auth extends Connection {
        
        public function __construct() {
            parent::__construct();
        }

        protected function getToken() {
            $headers = apache_request_headers();
            if (!isset($headers['Authorization'])) {
                Flight::halt(401, json_encode([
                    "message" => "Unauthenticated request",
                ]));
            };

            $authorization = $headers['Authorization'];
            $authorizationArray = explode(" ", $authorization);
            $token = $authorizationArray[1];
            $key = $_ENV['JWT_KEY'];

            try {
                return JWT::decode($token, new Key($key, 'HS256'));
            } catch (Exception $error) {
                Flight::halt(401, json_encode([
                    "message" => $error->getMessage(),
                ]));
            };
        }

        protected function validateToken() {
            $infoToken = $this->getToken();
            $query = $this->preConsult("SELECT id_privilegio FROM usuario WHERE id_usuario = ?");
            try {
                $query->execute([$infoToken->id_usuario]);
                if ($query->rowCount() !== 1) {
                    Flight::halt(404, json_encode([
                        "message" => "Usuario no valido !",
                    ]));
                }

                $privilegeAccount = $query->fetch(PDO::FETCH_OBJ);
                return $privilegeAccount->id_privilegio;

            } catch (Exception $error) {
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $error->getMessage(),
                ]));
            }
            

            // $rows = $query->fetch();

            // try {
            //     $query->execute([$infoToken->id_usuario]);
            //     $userAccount = $query->fetch(PDO::FETCH_OBJ);
    
            //     $this->array["validate"] = true;
            //     if ($userAccount->id_privilegio === $requiredPrivilege) $this->array["privilege"] = true;

            //     Flight::json($this->array);

            // } catch (Exception $error) {
            //     Flight::halt(401, json_encode([
            //         "message" => "Error: ". $error->getMessage(),
            //     ]));
            // }

            // $rows = $query->fetchColumn(); // respaldo
            // return $rows; // respaldo

            // if ($query->rowCount() === 1) {
            //     return Flight::json(true);
            // }
            // return Flight::json(false);
        }

        public function auth() {
            $user = Flight::request()->data->email;
            $password = Flight::request()->data->password;
            $statementEmail = $this->preConsult(
                "SELECT usuario.id_usuario, usuario.nombre_usuario, usuario.clave_usuario, usuario.id_privilegio,
                SPLIT_PART(funcionario.nombres_funcionario, ' ', 1) || ' ' || funcionario.ap_funcionario AS nombres_funcionario
                FROM usuario 
                INNER JOIN funcionario ON funcionario.id_funcionario = usuario.id_funcionario
                WHERE nombre_usuario = ?"
            );

            try {
                $statementEmail->execute([$user]);
                if ($statementEmail->rowCount() === 1) {
                    $userAccount = $statementEmail->fetch(PDO::FETCH_OBJ);

                    if (md5($password) !== $userAccount->clave_usuario) {
                        Flight::halt(406, json_encode([
                            "message" => "La contraseña ingresada es incorrecta",
                            "statusText" => "error password"
                        ]));
                    }
                    
                    $now = strtotime("now");
                    $key = $_ENV['JWT_KEY'];
                    $payload = [
                        'exp' => $now + 3600,
                        // 'exp' => $now + 20,
                        'id_usuario' => $userAccount->id_usuario,
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

                Flight::halt(406, json_encode([
                    "message" => "El e-mail ingresado no tiene cuenta de usuario",
                    "statusText" => "error email"
                ]));
                
            } catch (Exception $error) {
                Flight::halt(404, json_encode([
                    "message" => "Authentication erro: ".$error->getMessage(),
                    "statusText" => "error"
                ]));

            } finally {
                $this->closeConnection();
            }
        }

        public function validateSession() {
            try {
                $privilege = $this->validateToken();
                // if ($privilege !== null) {
                //     return Flight::json(true);
                // }

            } catch ( Exception $error) {
                Flight::halt(401, json_encode([
                    "message" => $error->getMessage(),
                ]));

            } finally {
                $this->closeConnection();
            }
        }

    }

?>