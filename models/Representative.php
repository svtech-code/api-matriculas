<?php
    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;

    class Representative extends Auth {
        public function __construct() {
            parent::__construct();
        }
        
        public function getRepresentativeAll() {
            $this->validateToken();
            $statementRepresentative = $this->preConsult(
                ""
            );

            try {

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }
        }

        // modificar acceso a la base de datos
        public function getNameRepresentative($rut_representative) {
            $this->validateToken();
            $statementRepresentative = $this->preConsult(
                "SELECT id_apoderado,
                (nombres_apoderado || ' ' || ap_apoderado || ' ' || am_apoderado) AS nombres_apoderado
                FROM apoderado
                WHERE rut_apoderado = ?;"
            );

            try {
                $statementRepresentative->execute([$rut_representative]);
                $representative = $statementRepresentative->fetch(PDO::FETCH_OBJ);
                if (!$representative) {
                    Flight::halt(200, json_encode([
                        "message" => "Sin registro de apoderado(a) !"
                    ]));
                }

                $this->array = [
                    "id" => $representative->id_apoderado,
                    "nombres" => $representative->nombres_apoderado,
                ];
                Flight::json($this->array);

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }
        }

        // trabajar en el update del apoderado
        // trabajar en el ingreso de apoderado
    }


?>