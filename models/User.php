<?php

namespace Models;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Flight;
use PDO;
use Exception;

class User {
    private $connection;
    private $array = [];

    public function __construct() {
        Flight::register(
            'connection',
            'PDO',
            array('pgsql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']));
        $this->connection = Flight::connection();
    }

    public function getToken() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            Flight::halt(403, json_encode([
                "message" => "Unauthenticated request",
                "status" => "error"
            ]));
        };

        $authorization = $headers['Authorization'];
        $authorizationArray = explode(" ", $authorization);
        $token = $authorizationArray[1];
        $key = $_ENV['JWT_KEY'];

        try {
            return JWT::decode($token, new Key($key, 'HS256'));
        } catch (Exception $error) {
            Flight::halt(403, json_encode([
                "message" => $error->getMessage(),
                "status" => "error"
            ]));
        };
    }

    function validateToken() {
        $infoToken = $this->getToken();
        $connection = Flight::connection();
        $query = $connection->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $query->execute([$infoToken->id_usuario]);
        $rows = $query->fetchColumn();
        return $rows;
    }

    public function auth() {
        $user = Flight::request()->data->email;
        $password = Flight::request()->data->password;

        $query = $this->connection->prepare("SELECT * FROM usuario WHERE nombre_usuario = ? AND clave_usuario = ?");

        try {
            // Agregar validación, para cuando el usuario es incorrecto
            $query->execute([$user, md5($password)]);
            $userAccount = $query->fetch(PDO::FETCH_ASSOC);
            $now = strtotime("now");
            $key = $_ENV['JWT_KEY'];
            $payload = [
                'exp' => $now + 3600,
                'id_usuario' => $userAccount['id_usuario'],
                'id_privilegio' => $userAccount['id_privilegio']
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');
            $this->array = [
                "token" => $jwt,
                "message" => "cuenta válida",
                "status" => "success"
            ];

        } catch (Exception $error) {
            $this->array = [
                "message" => "No se pudo validar la cuenta de usuario ingresada: ". $error->getMessage(),
                "status" => "error"
            ];
        } finally {
            Flight::json($this->array);
        }
    }

    public function getUserAll() {
        if (!$this->validateToken()) {
            Flight::halt(403, json_encode([
                "message" => "Unautorized"
            ]));
        };

        $query = $this->connection->prepare("SELECT * FROM usuario");
        
        try {
            $query->execute();
            $data = $query->fetchAll();
            foreach($data as $row) {
                $this->array[] = [
                    "id_usuario" => $row['id_usuario'],
                    "nombre_usuario" => $row['nombre_usuario'],
                    "clave_usuario" => $row['clave_usuario'],
                    "id_funcionario" => $row['id_funcionario'],
                    "id_privilegio" => $row['id_privilegio'],
                    "fecha_creacion" => $row['fecha_creacion'],
                    "id_estado" => $row['id_estado'],
                    "fecha_ingreso" => $row['fecha_ingreso']
                ];
            };
            $this->array["status"] = ["success"];
        } catch (Exception $error) {
            $this->array = [
                "message" => "Error en la consulta de datos: ". $error->getMessage(),
                "status" => "Error"
            ];
        } finally {
            Flight::json($this->array);
        };
    }

    public function getUserOne($id_usuario) {
        $query = $this->connection->prepare("SELECT * from usuario where id_usuario = ?");

        try {
            $query->execute([$id_usuario]);
            $data = $query->fetch();
            $this->array = [
                "data" => [
                    "id_usuario" => $data['id_usuario'],
                    "nombre_usuario" => $data['nombre_usuario'],
                    "clave_usuario" => $data['clave_usuario'],
                    "id_funcionario" => $data['id_funcionario'],
                    "id_privilegio" => $data['id_privilegio'],
                    "fecha_creacion" => $data['fecha_creacion'],
                    "id_estado" => $data['id_estado'],
                    "fecha_ingreso" => $data['fecha_ingreso']
                ],
                "status" => "success"
            ];

        } catch (Exception $error) {
            $this->array = [
                "message" => "Error en la consulta: ". $error->getMessage(),
                "status" => "error"
            ];
        } finally {
            Flight::json($this->array);
        }
    }

    public function setUser() {
        if (!$this->validateToken()) {
            Flight::halt(403, json_encode([
                "message" => "Unautorized",
                "status" => "error"
            ]));
        };

        $nombre_usuario = Flight::request()->data->nombre_usuario;
        $clave_usuario = Flight::request()->data->clave_usuario;
        $id_funcionario = Flight::request()->data->id_funcionario;
        $id_privilegio = Flight::request()->data->id_privilegio;

        $query = $this->connection->prepare("INSERT INTO usuario 
            (nombre_usuario, clave_usuario, id_funcionario, id_privilegio, fecha_creacion) 
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        
        try {
            $query->execute([$nombre_usuario, $clave_usuario, $id_funcionario, $id_privilegio]);
            $this->array = [
                "message" => "Registro almacenado con exito",
                "status" => "success"
            ];
        } catch (Exception $error) {
            $this->array = [
                "message" => "Error al almacenar el registro: ". $error->getMessage(),
                "status" => "error"
            ];
        } finally {
            Flight::json($this->array);
        };
    }

    public function updateUser() {
        if (!$this->validateToken()) {
            Flight::halt(403, json_encode([
                "message" => "Unautorized",
                "status" => "error"
            ]));
        };

        $id_usuario = Flight::request()->data->id_usuario;
        $nombre_usuario = Flight::request()->data->nombre_usuario;
        $clave_usuario = Flight::request()->data->clave_usuario;
        $id_funcionario = Flight::request()->data->id_funcionario;
        $id_privilegio = Flight::request()->data->id_privilegio;

        $query = $this->connection->prepare("UPDATE usuario 
            SET nombre_usuario = ?, clave_usuario = ?, id_funcionario = ?, id_privilegio = ? 
            WHERE id_usuario = ?");
        
        try {
            $query->execute([$nombre_usuario, $clave_usuario, $id_funcionario, $id_privilegio, $id_usuario]);
            $this->array = [
                "message" => "Registro actualizado con éxito",
                "status" => "success"
            ];
        } catch (Exception $error) {
            $this->array = [
                "message" => "Error al almacenar el registro: ". $error->getMessage(),
                "status" => "error"
            ];
        } finally {
            Flight::json($this->array);
        };
    }

    public function deleteUser($id_usuario) {
        if (!$this->validateToken()) {
            Flight::halt(403, json_encode([
                "message" => "Unautorized",
                "status" => "error"
            ]));
        };

        $query = $this->connection->prepare("DELETE FROM usuario WHERE id_usuario = ?");

        try {
            $query->execute([$id_usuario]);
            $this->array = [
                "message" => "Registro eliminado con éxito",
                "status" => "success"
            ];
        } catch (Exception $error) {
            $this->array = [
                "message" => "Error al eliminar el registro: ". $error->getMessage(),
                "status" => "error"
            ];
        } finally {
            Flight::json($this->array);
        }
    }
}


?>