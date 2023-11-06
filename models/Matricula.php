<?php   
    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;

    class Matricula extends Auth {
        public function __construct() {
            parent::__construct();
        }

        // Método para obtener el estado del periodo de matricula
        public function getPeriodoMatricula() {
            $statementPeriodoMatricula = $this->preConsult(
                "SELECT estado 
                FROM libromatricula.periodo_matricula
                WHERE anio_lectivo = EXTRACT(YEAR FROM CURRENT_DATE);"
            );

            try {
                $statementPeriodoMatricula->execute();
                $response = $statementPeriodoMatricula->fetch(PDO::FETCH_OBJ);
                $this->array = ["state" => $response->estado];
                Flight::json($this->array);

            } catch(Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            }
        }

        // método para obtener la cantidad de matriculas y retiros del periodo lectivo
        public function getCountAltasBajas($periodo) {
            $this->validateToken();

            $statmentCountAltasBajas = $this->preConsult(
                "SELECT COUNT(CASE WHEN m.id_estado_matricula = 1 THEN 1 ELSE NULL END) AS altas,
                COUNT(CASE WHEN m.id_estado_matricula = 4 THEN 1 ELSE NULL END) AS bajas
                FROM libromatricula.registro_matricula AS m
                WHERE m.anio_lectivo_matricula = ?;"
            );

            try {
                $statmentCountAltasBajas->execute([intval($periodo)]);
                $countAltasBajas = $statmentCountAltasBajas->fetch(PDO::FETCH_OBJ);
                $this->array = [
                    "altas" => $countAltasBajas->altas,
                    "bajas" => $countAltasBajas->bajas
                ];
                Flight::json($this->array);

            } catch(Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            }
        }

        // método para obtener lista de matriculas
        public function getMatriculaAll($periodo) {
            $this->validateToken();

            // Ver como manejar la condicion del año lectivo
            $statmentMatricula = $this->preConsult(
                "SELECT m.id_registro_matricula, m.numero_matricula, (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, 
                e.ap_estudiante, e.am_estudiante, (CASE WHEN e.nombre_social IS NULL THEN e.nombres_estudiante ELSE
                '(' || e.nombre_social || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
                COALESCE(to_char(e.fecha_nacimiento, 'DD / MM / YYYY'), 'Sin registro') AS fecha_nacimiento,
                to_char(m.fecha_alta_matricula, 'DD / MM / YYYY') AS fecha_alta,
                to_char(m.fecha_baja_matricula, 'DD / MM / YYYY') AS fecha_baja,
                to_char(m.fecha_matricula, 'DD / MM / YYYY') AS fecha_matricula,
                CASE WHEN e.sexo = 'M' THEN 'MASCULINO' ELSE 'FEMENINO' END AS sexo, UPPER(estado.nombre_estado) AS estado, m.grado,
                --CASE WHEN m.id_curso IS NULL THEN m.grado::text ELSE curso.curso END AS curso, 
                (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) as rut_apoderado_titular,
                (apt.nombres_apoderado || ' ' || apt.ap_apoderado || ' ' || apt.am_apoderado) AS apoderado_titular,
                ('+569-' || apt.telefono) AS telefono_titular,
                (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_apoderado_suplente,
                (aps.nombres_apoderado || ' ' || aps.ap_apoderado || ' ' || aps.am_apoderado) AS apoderado_suplente,
                ('+569-' || aps.telefono) AS telefono_suplente
                FROM libromatricula.registro_matricula AS m
                INNER JOIN estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN estado ON estado.id_estado = m.id_estado_matricula
                LEFT JOIN apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
                --LEFT JOIN curso ON curso.id_curso = m.id_curso
                WHERE m.anio_lectivo_matricula = ?
                ORDER BY m.numero_matricula ASC;"
            );

            try {
                $statmentMatricula->execute([intval($periodo)]);
                $matriculas = $statmentMatricula->fetchAll(PDO::FETCH_OBJ);
                foreach($matriculas as $matricula) {
                    $this->array[] = [
                        "id" => $matricula->id_registro_matricula,
                        "matricula" => $matricula->numero_matricula,
                        "rut" => $matricula->rut_estudiante,
                        "paterno" => $matricula->ap_estudiante,
                        "materno" => $matricula->am_estudiante,
                        "nombres" => $matricula->nombres_estudiante,
                        "fecha_nacimiento" => $matricula->fecha_nacimiento,
                        "fecha_alta" => $matricula->fecha_alta,
                        "fecha_baja" => $matricula->fecha_baja,
                        "fecha_matricula" => $matricula->fecha_matricula,
                        "sexo" => $matricula->sexo,
                        "estado" => $matricula->estado,
                        "grado" => $matricula->grado,
                        "rut_titular" => $matricula->rut_apoderado_titular,
                        "apoderado_titular" => $matricula->apoderado_titular,
                        "telefono_titular" => $matricula->telefono_titular,
                        "rut_suplente" => $matricula->rut_apoderado_suplente,
                        "apoderado_suplente" => $matricula->apoderado_suplente,
                        "telefono_suplente" => $matricula->telefono_suplente,
                    ];
                }
                Flight::json($this->array);
                
            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }
        }

        // método para obtener los datos de una matricula
        public function getMatricula($id) {
            $this->validateToken();

            $statementMatricula = $this->preConsult(
                "SELECT m.numero_matricula, m.fecha_matricula, m.grado,
                m.id_estudiante, e.rut_estudiante, e.dv_rut_estudiante,
                (CASE WHEN e.nombre_social IS NULL THEN e.nombres_estudiante
                ELSE '(' || e.nombre_social || ') ' || e.nombres_estudiante END
                || ' ' || e.ap_estudiante || ' ' || e.am_estudiante) AS nombres_estudiante,
                m.id_apoderado_titular, apt.rut_apoderado AS rut_titular, apt.dv_rut_apoderado AS dv_rut_titular,
                (apt.nombres_apoderado || ' ' || apt.ap_apoderado || ' ' || apt.am_apoderado) AS nombres_titular,
                m.id_apoderado_suplente, aps.rut_apoderado AS rut_suplente, aps.dv_rut_apoderado AS dv_rut_suplente,
                (aps.nombres_apoderado || ' ' || aps.ap_apoderado || ' ' || aps.am_apoderado) AS nombres_suplente
                FROM libromatricula.registro_matricula AS m
                LEFT JOIN estudiante AS e ON e.id_estudiante = m.id_estudiante 
                LEFT JOIN apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
                WHERE m.id_registro_matricula = ?;"
            );

            try {
                $statementMatricula->execute([intval($id)]);
                $matricula = $statementMatricula->fetch(PDO::FETCH_OBJ);
                $this->array = [
                    "numero_matricula" => $matricula->numero_matricula,
                    "fecha_matricula" => $matricula->fecha_matricula,
                    "grado" => $matricula->grado,
                    "id_estudiante" => $matricula->id_estudiante,
                    "rut_estudiante" => $matricula->rut_estudiante,
                    "dv_rut_estudiante" => $matricula->dv_rut_estudiante,
                    "nombres_estudiante" => $matricula->nombres_estudiante,
                    "id_apoderado_titular" => $matricula->id_apoderado_titular,
                    "rut_titular" => $matricula->rut_titular,
                    "dv_rut_titular" => $matricula->dv_rut_titular,
                    "nombres_titular" => $matricula->nombres_titular,
                    "id_apoderado_suplente" => $matricula->id_apoderado_suplente,
                    "rut_suplente" => $matricula->rut_suplente,
                    "dv_rut_suplente" => $matricula->dv_rut_suplente,
                    "nombres_suplente" => $matricula->nombres_suplente,
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

        // método para obtener el número de matricula correlativo por nivel
        protected function getNumberMatricula($grade) {
            if ($grade >= 1 && $grade <= 4) {
                $statementNumberMatricula = $this->preConsult(
                    "SELECT COALESCE(MAX(numero_matricula) + 1, 1) AS numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 1 AND 4 AND anio_lectivo_matricula = 2024;"
                );
            } elseif ($grade >= 7 && $grade <= 8) {
                $statementNumberMatricula = $this->preConsult(
                    "SELECT COALESCE(MAX(numero_matricula) + 1, 1) AS numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 7 AND 8 AND anio_lectivo_matricula = 2024;"
                );
            }

            try {
                $statementNumberMatricula->execute();
                $n_matricula = $statementNumberMatricula->fetch(PDO::FETCH_OBJ);
                return $n_matricula->numero_matricula;

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            } 
            // no cerrar la conexion aún, ya que la utilizare en otro metodo
        }

        // método para comprobar si un estudiante ya se encuentra matriculado
        protected function verifStudentMatricula($id_estudiante, $periodo) {
            $statementVerifyStudent = $this->preConsult(
                "SELECT id_registro_matricula
                FROM libromatricula.registro_matricula
                WHERE id_estudiante = ? AND anio_lectivo_matricula = ?"
            );

            try {
                $statementVerifyStudent->execute([intval($id_estudiante), intval($periodo)]);
                $verify = $statementVerifyStudent->fetch(PDO::FETCH_OBJ);
                if ($verify) {
                    Flight::halt(403, json_encode([
                        "message" => "Estudiante matriculado !"
                    ]));
                }

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            }
        }

        // método para registrar una matrícula
        public function setMatricula() {
            $this->validateToken();
            $matricula = Flight::request()->data;
            $n_matricula = $this->getNumberMatricula($matricula->grado);

            $this->verifStudentMatricula(
                $matricula->id_estudiante ? intval($matricula->id_estudiante) : null,
                $matricula->anio_lectivo ? intval($matricula->anio_lectivo) : null
            );

            $statementMatricula = $this->preConsult(
                "INSERT INTO libromatricula.registro_matricula
                (numero_matricula, id_estudiante, id_apoderado_titular, id_apoderado_suplente,
                grado, fecha_matricula, anio_lectivo_matricula)
                VALUES (?, ?, ?, ?, ?, ?, ?);"
            );

            try {
                $statementMatricula->execute([
                    intval($n_matricula), 
                    $matricula->id_estudiante ? intval($matricula->id_estudiante) : null , 
                    $matricula->id_titular ? intval($matricula->id_titular) : null,
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null, 
                    $matricula->grado ? intval($matricula->grado) : null, 
                    $matricula->fecha_matricula ? $matricula->fecha_matricula : null,
                    $matricula->anio_lectivo ? intval($matricula->anio_lectivo) : null
                ]);

                $this->array = ["numero_matricual" => $n_matricula];
                Flight::json($this->array);

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }

            
        }

        // método para actualizar una matrícula
        public function updateMatricula() {
            $this->validateToken();
            $matricula = Flight::request()->data;
            
            $statementUpdateMatricula = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET id_estudiante = ?, id_apoderado_titular = ?, 
                id_apoderado_suplente = ?, fecha_matricula = ?
                WHERE id_registro_matricula = ?;"
            );

            try {
                $statementUpdateMatricula->execute([
                    intval($matricula->id_estudiante),
                    $matricula->id_titular ? intval($matricula->id_titular) : null,
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null,
                    $matricula->fecha_matricula ? $matricula->fecha_matricula : null,
                    $matricula->id_matricula ? intval($matricula->id_matricula) : null,
                ]);

            } catch(Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }
        }




        









        // ------- trabajando en las funcionalidades
        // ver si será implementada, por lo que conlleva eliminar un registro de matricula !!!!! ------->
        public function deleteMatricula($id) {
            $this->validateToken();
            $statementDeleteMatricula = $this->preConsult(
                "DELETE FROM libromatricula.registro_matricula
                WHERE id_registro_matricula = ?;"
            );

            try {
                $statementDeleteMatricula->execute([intval($id)]);
                $this->array = ["message" => "success"];
                Flight::json($this->array);

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            } finally {
                $this->closeConnection();
            }
        }




        // ------- funcionalidades por trabajar



       


        public function exportMatricula() {

        }

        public function getCertificadoAlumnoRegular() {

        }

        public function getCertificadoMatricula() {

        }
        
    }



?>