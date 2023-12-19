<?php
    namespace Models;

    use Exception;
    use Flight;
    use PDO;

    class Connection {
        protected $connection;
        protected $array = [];

        public function __construct() {
            try {
                Flight::register(
                    'connection',
                    'PDO',
                    array('pgsql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']));
                $this->connection = Flight::connection();

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "connection error:" .$error->getMessage(),
                    "statusText" => "error"
                ]));
            }
        }

        public function preConsult($query) {
            return $this->connection->prepare($query);
        }

        public function closeConnection() {
            $this->connection = null;
        }

        // probando transacciones
        public function beginTransaction() {
            $this->connection->beginTransaction();
        }

        public function commit() {
            $this->connection->commit();
        }

        public function rollBack() {
            $this->connection->rollBack();
        }
    }


?>