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

        // método para registrar un apoderado
        public function setRepresentative() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // obtención de la data enviada por el cliente
            $representative = Flight::request()->data;

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementRepresentative = $this->preConsult(
                "INSERT INTO libromatricula.registro_apoderado
                (rut_apoderado, dv_rut_apoderado, apellido_paterno_apoderado,
                apellido_materno_apoderado, nombres_apoderado, 
                telefono_apoderado, direccion_apoderado) 
                VALUES (?, ?, ?, ?, ?, ?, ?);"
            );

            try {
                // se ejecuta la consulta
                $statementRepresentative->execute([
                    $representative->rut,
                    strtoupper($representative->dv_rut),
                    strtoupper($representative->paterno),
                    strtoupper($representative->materno),
                    strtoupper($representative->nombres),
                    $representative->telefono ? $representative->telefono : null,
                    $representative->direccion ? strtoupper($representative->direccion) : null,
                ]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // devolución de respuesta exitosa
                $this->array = ["message" => "success"];
                Flight::json($this->array);

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementRepresentative);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // Método para actualizar datos del apoderado
        public function updateRepresentative() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // obtención de la data enviada por el cliente
            $representative = Flight::request()->data;

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementUpdateRepresentative = $this->preConsult(
                "UPDATE libromatricula.registro_apoderado
                SET rut_apoderado = ?, dv_rut_apoderado = ?, apellido_paterno_apoderado = ?,
                apellido_materno_apoderado = ?, nombres_apoderado = ?, telefono_apoderado = ?,
                direccion_apoderado = ?, fecha_modificacion_apoderado = CURRENT_TIMESTAMP
                WHERE id_apoderado = ?;"
            );

            try {
                // se ejecuta la consulta
                $statementUpdateRepresentative->execute([
                    $representative->rut,
                    strtoupper($representative->dv_rut),
                    strtoupper($representative->paterno),
                    strtoupper($representative->materno),
                    strtoupper($representative->nombres),
                    $representative->telefono ? $representative->telefono : null,
                    $representative->direccion ? strtoupper($representative->direccion) : null,
                    intval($representative->id),
                ]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // devolución de respuesta exitosa
                $this->array = ["message" => "success"];
                Flight::json($this->array);

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementUpdateRepresentative);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }





        // trabajar estructura y actualización de formatos !!!!
        // =====================================================>>


        // método para obtener los datos del apoderado
        public function getRepresentative($rut_representative) {
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);


            $statementRepresentative = $this->preConsult(
                "SELECT a.id_apoderado, a.nombres_apoderado, a.apellido_paterno_apoderado,
                a.apellido_materno_apoderado, a.telefono_apoderado, a.direccion_apoderado
                FROM libromatricula.registro_apoderado AS a
                WHERE a.rut_apoderado = ?"
            );

            try {
                $statementRepresentative->execute([$rut_representative]);
                $representative = $statementRepresentative->fetch(PDO::FETCH_OBJ);
                if (!$representative) {
                    Flight::halt(200, json_encode([
                        "message" => "estudiante no registrado"
                    ]));
                }

                $this->array = [
                    "id"=> $representative->id_apoderado,
                    "nombres"=> $representative->nombres_apoderado,
                    "paterno"=> $representative->apellido_paterno_apoderado,
                    "materno"=> $representative->apellido_materno_apoderado,
                    "telefono"=> $representative->telefono_apoderado,
                    "direccion"=> $representative->direccion_apoderado,
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

        // método para obtener el nombre 
        public function getNameRepresentative($rut_representative) {
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            $statementRepresentative = $this->preConsult(
                "SELECT id_apoderado, (nombres_apoderado || ' ' || apellido_paterno_apoderado
                || ' ' || apellido_materno_apoderado) AS nombres_apoderado
                FROM libromatricula.registro_apoderado
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

        // método para verificar el registro de un apoderado
        // ver si es necesario
        // protected function verifyRepresentative()

        

        

        // trabajar en el update del apoderado
        // trabajar en el ingreso de apoderado



        // ======> trabajando
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
    }


?>