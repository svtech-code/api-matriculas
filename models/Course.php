<?php

    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;

    class Course extends Auth {
        public function __construct() {
            parent::__construct();
        }

        // método para obtener un listado de las matriculas y sus respectivos cursos
        public function getCourseAll($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 4]);

            // sentencia SQL
            $statementCourse = $this->preConsult(
                "SELECT m.id_registro_matricula, m.numero_matricula, (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, 
                ((CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
                '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) || ' ' || 
                e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante  ) AS nombres_estudiante,
                to_char(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta,
                to_char(m.fecha_baja_matricula, 'DD/MM/YYYY') AS fecha_baja,
                to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula,
                CASE WHEN e.sexo_estudiante = 'M' THEN 'MASCULINO' ELSE 'FEMENINO' END AS sexo, UPPER(est.estado) AS estado, 
                m.grado, (c.grado_curso::text || c.letra_curso) AS curso
                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN libromatricula.registro_estado AS est ON est.id_estado = m.id_estado_matricula
                LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                WHERE m.anio_lectivo_matricula = ?
                ORDER BY m.numero_matricula DESC;"
            );

            try {
                // se ejecuta la consulta
                $statementCourse->execute([intval($periodo)]);

                // se obtiene un objeto con los datos de la consutla
                $courses = $statementCourse->fetchAll(PDO::FETCH_OBJ);

                // se recorre el objeto para obtener un array con todos los datos de la consulta
                foreach($courses as $course) {
                    $this->array[] = [
                        "id" => $course->id_registro_matricula,
                        "matricula" => $course->numero_matricula,
                        "rut" => $course->rut_estudiante,
                        "nombres_estudiante" => $course->nombres_estudiante,
                        "fecha_alta" => $course->fecha_alta,
                        "fecha_baja" => $course->fecha_baja,
                        "fecha_matricula" => $course->fecha_matricula,
                        "sexo" => $course->sexo,
                        "estado" => $course->estado,
                        "grado" => $course->grado,
                        "curso"=> $course->curso,
                    ];
                }

                // se devuelve un array con todos los datos de matricula
                Flight::json($this->array);

            } catch (Exception $error) {
                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();

            }

        }

        // método para obtener lista de cursos // grados y sus letras correspondientes
        public function getListCourse($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 4]);

            // sentencia SQL
            $statementListCourse = $this->preConsult(
                "SELECT grado_curso AS grado,
                json_agg(DISTINCT CONCAT(grado_curso, letra_curso)) AS letras
                FROM libromatricula.registro_curso
                WHERE periodo_escolar = ?
                GROUP BY grado_curso ORDER BY grado_curso;"
            );

            try {
                // se ejecuta la consulta
                $statementListCourse->execute([$periodo]);

                // se obtiene un objeto con los datos de la consulta
                $listCourse = $statementListCourse->fetchAll(PDO::FETCH_OBJ);

                // se recorre el objeto para obtener un array con los datos consultados
                foreach ($listCourse as $course) {
                    $this->array[] = [
                        "grado" => $course->grado,
                        "letra" => $course->letras,
                    ];
                }

                // se devuelve un array con todos los datos de matricula
                Flight::json($this->array);

            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementListCourse);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // método para consultar fecha de inicio de clases del periodo
        public function getClassStartDate($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 4]);

            // sentencia SQL
            $statementClassStartDate = $this->preConsult(
                //"SELECT p.fecha_inicio_clases
                "SELECT to_char(p.fecha_inicio_clases, 'YYYY/MM/DD') AS fecha_inicio_clases
                FROM libromatricula.periodo_matricula AS p
                WHERE p.anio_lectivo = ?;"
            );

            try {
                // se ejecuta la consulta
                $statementClassStartDate->execute([$periodo]);

                // se obtiene un objeto del resultado de la consulta
                $startDate = $statementClassStartDate->fetch(PDO::FETCH_OBJ);

                // devolvemos como respuesta la fecha de inicio de clases
                Flight::json($startDate->fecha_inicio_clases);


            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementClassStartDate);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                $this->closeConnection();
            }

        }

        // método para actualizar el curso de una matricula
        public function updateLetterCourse() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 4]);
            
            // obtención de los datos para actualización
            $course = Flight::request()->data;

            // obtención de letra y grado por separados
            $grado = substr($course->curso, 0, 1);
            $letra = substr($course->curso, 1, 1);

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementUpdateLetterCourse = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET id_curso = (
                    SELECT id_curso
                    FROM libromatricula.registro_curso
                    WHERE grado_curso = ? 
                    AND letra_curso = ? 
                    AND periodo_escolar = ?
                    ), fecha_alta_matricula = ?
                WHERE id_registro_matricula = ? 
                AND anio_lectivo_matricula = ?;"
            );

            try {
                // se ejecuta la consulta
                $statementUpdateLetterCourse->execute([
                    $grado,                 // grado del curso
                    $letra,                 // letra del curso
                    $course->periodo,       // periodo del curso
                    $course->fechaAlta,     // fecha correspondiente a la asignacion del curso
                    $course->idMatricula,   // id de la matricula
                    $course->periodo        // periodo de la matricula
                ]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // retorno del curso asignado
                Flight::json($course->curso);

            } catch (Exception $error) {
                // revertir transacción en caso de error
                $this->rollBack();
                // ========================>

                // obtencuión de mensaje de error de potgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementUpdateLetterCourse);

                // excepción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError, 
                ]));


            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // public function respaldo_getListCourse($periodo) {
        //     // se valida el token del usuario
        //     $this->validateToken();

        //     // sentencia SQL
        //     $statementListCourse = $this->preConsult(
        //         "SELECT DISTINCT letra_curso
        //         FROM libromatricula.registro_curso
        //         WHERE periodo_escolar = ?
        //         GROUP BY letra_curso;"
        //     );

        //     try {
        //         // se ejecuta la consulta
        //         $statementListCourse->execute([intval($periodo)]);

        //         // se obtiene un objeto con los datos de la consutla
        //         // $grades = $statementGrade->fetchAll(PDO::FETCH_OBJ);
        //         $listCourse = $statementListCourse->fetchAll(PDO::FETCH_COLUMN);

        //         // se recorre el objeto para obtener un array con todos los datos de la consulta
        //         // foreach($grades as $grade) {
        //         //     $this->array[] = [
        //         //         "curso" => $grade->letra_curso,
        //         //     ];
        //         // }
        //         $this->array = ["listCourse" => $listCourse];

        //         // se devuelve un array con todos los datos de matricula
        //         Flight::json($this->array);

        //     } catch (Exception $error) {
        //         // expeción personalizada para errores
        //         Flight::halt(404, json_encode([
        //             "message" => "Error: ". $error->getMessage()
        //         ]));

        //     } finally {
        //          // cierre de la conexión con la base de datos
        //          $this->closeConnection();
        //     }

        // }

        
            
        
    }



?>