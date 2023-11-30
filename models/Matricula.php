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
                e.apellido_paterno_estudiante, e.apellido_materno_estudiante, (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
                '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
                COALESCE(to_char(e.fecha_nacimiento_estudiante, 'DD / MM / YYYY'), 'Sin registro') AS fecha_nacimiento,
                to_char(m.fecha_alta_matricula, 'DD / MM / YYYY') AS fecha_alta,
                to_char(m.fecha_baja_matricula, 'DD / MM / YYYY') AS fecha_baja,
                to_char(m.fecha_matricula, 'DD / MM / YYYY') AS fecha_matricula,
                CASE WHEN e.sexo_estudiante = 'M' THEN 'MASCULINO' ELSE 'FEMENINO' END AS sexo, UPPER(est.estado) AS estado, 
                m.grado, (c.grado_curso::text || c.letra_curso) AS curso,
                (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) as rut_apoderado_titular,
                (apt.nombres_apoderado || ' ' || apt.apellido_paterno_apoderado || ' ' || apt.apellido_materno_apoderado) AS apoderado_titular,
                ('+569-' || apt.telefono_apoderado) AS telefono_titular,
                (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_apoderado_suplente,
                (aps.nombres_apoderado || ' ' || aps.apellido_paterno_apoderado || ' ' || aps.apellido_materno_apoderado) AS apoderado_suplente,
                ('+569-' || aps.telefono_apoderado) AS telefono_suplente
                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN libromatricula.registro_estado AS est ON est.id_estado = m.id_estado_matricula
                LEFT JOIN libromatricula.registro_apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN libromatricula.registro_apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
                LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                WHERE m.anio_lectivo_matricula = ?
                ORDER BY m.numero_matricula DESC;"
            );

            try {
                $statmentMatricula->execute([intval($periodo)]);
                $matriculas = $statmentMatricula->fetchAll(PDO::FETCH_OBJ);
                foreach($matriculas as $matricula) {
                    $this->array[] = [
                        "id" => $matricula->id_registro_matricula,
                        "matricula" => $matricula->numero_matricula,
                        "rut" => $matricula->rut_estudiante,
                        "paterno" => $matricula->apellido_paterno_estudiante,
                        "materno" => $matricula->apellido_materno_estudiante,
                        "nombres" => $matricula->nombres_estudiante,
                        "fecha_nacimiento" => $matricula->fecha_nacimiento,
                        "fecha_alta" => $matricula->fecha_alta,
                        "fecha_baja" => $matricula->fecha_baja,
                        "fecha_matricula" => $matricula->fecha_matricula,
                        "sexo" => $matricula->sexo,
                        "estado" => $matricula->estado,
                        "grado" => $matricula->grado,
                        "curso"=> $matricula->curso,
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
                (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante
                ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END
                || ' ' || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                m.id_apoderado_titular, apt.rut_apoderado AS rut_titular, apt.dv_rut_apoderado AS dv_rut_titular,
                (apt.nombres_apoderado || ' ' || apt.apellido_paterno_apoderado || ' ' || apt.apellido_materno_apoderado) AS nombres_titular,
                m.id_apoderado_suplente, aps.rut_apoderado AS rut_suplente, aps.dv_rut_apoderado AS dv_rut_suplente,
                (aps.nombres_apoderado || ' ' || aps.apellido_paterno_apoderado || ' ' || aps.apellido_materno_apoderado) AS nombres_suplente
                FROM libromatricula.registro_matricula AS m
                LEFT JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante 
                LEFT JOIN libromatricula.registro_apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN libromatricula.registro_apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
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
        protected function getNumberMatricula($grade, $periodo) {

            if ($grade >= 1 && $grade <= 4) {
                $statementNumberMatricula = $this->preConsult(
                    "SELECT numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 1 AND 4 AND anio_lectivo_matricula = ?
                    ORDER BY numero_matricula ASC;"
                );
            } elseif ($grade >= 7 && $grade <= 8) {
                $statementNumberMatricula = $this->preConsult(
                    "SELECT numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 7 AND 8 AND anio_lectivo_matricula = ?
                    ORDER BY numero_matricula ASC;"
                );
            }

            try {
                $statementNumberMatricula->execute([$periodo]);
                $rango_matricula = $statementNumberMatricula->fetchAll(PDO::FETCH_COLUMN);

                // Si no hay datos en la tabla, comenzar desde 1
                if (empty($rango_matricula)) {
                    return 1;
                }

                // obtener los valores del rango inicial y final
                $rango_inicial = 1;
                $rango_final = max($rango_matricula);
                $numero_matricula = $rango_inicial;

                // recorrer el rango de numero de matriculas obtenido en la consulta
                foreach ($rango_matricula as $rango) {
                    // verificar si el número esta dentro del rango
                    if ($rango >= $rango_inicial && $rango <= $rango_final) {
                        // si el número es el correlativo esperado, incrementar el correlativo
                        if ($rango == $numero_matricula) {
                            $numero_matricula++;
                        } else {
                            // si falta un número en el rango, ese será el correlativo
                            break;
                        }
                    }
                }

                // si todos los números estan presentes, el correlativo será el siguiente después del máximo en el rango
                if ($numero_matricula > $rango_final) {
                    $numero_matricula = $rango_final + 1;
                }

                return $numero_matricula;

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
                        "message" => "Estudiante ya matriculado !!"
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
            $n_matricula = $this->getNumberMatricula($matricula->grado, $matricula->anio_lectivo);

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
                    intval($matricula->id_estudiante), 
                    intval($matricula->id_titular),
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null, 
                    intval($matricula->grado), 
                    $matricula->fecha_matricula,
                    intval($matricula->anio_lectivo),
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

        // =======> desencadenar log de cambio de apoderados
        // método para actualizar una matrícula
        public function updateMatricula() {
            $this->validateToken();
            $matricula = Flight::request()->data;
            $newLevel = "";

            // obtener nivel educativo a actualizar
            if ($matricula->grado >= 1 && $matricula->grado <= 4) $newLevel = "Media";
            if ($matricula->grado >= 7 && $matricula->grado <= 8) $newLevel = "Basica";

            // comprobar cambio de nivel
            $statementCheckGrade = $this->preConsult(
                "SELECT CASE
                WHEN grado IN (7,8) THEN 'Basica'
                WHEN grado BETWEEN 1 AND 4 THEN 'Media'
                END AS nivel_educativo
                FROM libromatricula.registro_matricula
                WHERE id_registro_matricula = ?;"
            );
            
            // ver como manejar el numero de matricula
            $statementUpdateMatricula = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET numero_matricula = ?, id_estudiante = ?, id_apoderado_titular = ?, 
                id_apoderado_suplente = ?, grado = ?, fecha_matricula = ?,
                fecha_modificacion_matricula = CURRENT_TIMESTAMP
                WHERE id_registro_matricula = ?;"
            );

            try {
                // se obtiene el nivel de la matricula
                $statementCheckGrade->execute([$matricula->id_matricula]);
                $oldLevel = $statementCheckGrade->fetch(PDO::FETCH_OBJ);

                // se compara los niveles y se asigna el numero de matricula
                $numero_matricula = ($newLevel === $oldLevel->nivel_educativo) 
                    ? $matricula->n_matricula
                    : $this->getNumberMatricula($matricula->grado, $matricula->anio_lectivo);

                // actulizacion de la matricula
                $statementUpdateMatricula->execute([
                    $numero_matricula,
                    intval($matricula->id_estudiante),
                    intval($matricula->id_titular),
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null,
                    intval($matricula->grado),
                    $matricula->fecha_matricula,
                    intval($matricula->id_matricula),
                ]);

                // se devuelve el numero de matricula
                Flight::json($numero_matricula);

            } catch(Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }
        }




        









        // ------- trabajando en las funcionalidades





        // ------- funcionalidades por trabajar


        
    }



?>