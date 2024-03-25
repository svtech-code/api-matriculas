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
            $this->validatePrivilege([1, 2, 3, 4]);

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
                m.grado, (c.grado_curso::text || c.letra_curso) AS curso, m.numero_lista_curso
                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN libromatricula.registro_estado AS est ON est.id_estado = m.id_estado_matricula
                LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                INNER JOIN libromatricula.periodo_matricula AS p ON p.anio_lectivo = ?
                WHERE m.anio_lectivo_matricula = ?
                ORDER BY
                    CASE WHEN m.id_curso IS NULL THEN 0 ELSE 1 END,
                    CASE WHEN NOT p.autocorrelativo_listas THEN c.grado_curso END,
                    CASE WHEN NOT p.autocorrelativo_listas THEN c.letra_curso END,
                    CASE WHEN NOT p.autocorrelativo_listas THEN m.numero_lista_curso END,
                    CASE WHEN p.autocorrelativo_listas THEN unaccent(e.apellido_paterno_estudiante) END,
                    CASE WHEN p.autocorrelativo_listas THEN unaccent(e.apellido_materno_estudiante) END,
                    CASE WHEN p.autocorrelativo_listas THEN unaccent(e.nombres_estudiante) END;"
            );

            try {
                // se ejecuta la consulta
                $statementCourse->execute([intval($periodo), intval($periodo)]);

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
                        "curso" => $course->curso,
                        "n_lista" => $course->numero_lista_curso,
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
            $this->validatePrivilege([1, 2, 3, 4]);

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
            $this->validatePrivilege([1, 2, 3, 4]);

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
            $this->validatePrivilege([1, 2]);

            // se obtiene el id_usuario del token
            $usserId = $this->getToken()->id_usuario;
            
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
                "UPDATE libromatricula.registro_matricula AS m
                SET id_curso = c.id_curso,
                numero_lista_curso = CASE 
                                        WHEN p.autocorrelativo_listas THEN NULL
                                        ELSE c.numero_lista_curso + 1
                                    END,
                fecha_alta_matricula = ?,
                fecha_baja_matricula = ?,
                fecha_modificacion_matricula = CURRENT_TIMESTAMP,
                id_usuario_responsable = ?
                FROM libromatricula.registro_curso AS c
                JOIN libromatricula.periodo_matricula AS p ON p.anio_lectivo = ?
                WHERE 
                    m.id_registro_matricula = ? 
                    AND m.anio_lectivo_matricula = ?
                    AND c.grado_curso = ?
                    AND c.letra_curso = ?
                    AND c.periodo_escolar = ?
                RETURNING c.numero_lista_curso + 1;"
            );

            try {
                // se ejecuta la consulta
                $statementUpdateLetterCourse->execute([
                    $course->fechaAlta,     // para fecha_alta_matricula
                    ($course->fechaBaja ? $course->fechaBaja : null),     // para fecha_baja_matricula
                    $usserId,               // para id_usuario_responsable
                    $course->periodo,       // para p.anio_lectivo
                    $course->idMatricula,   // para m.id_registro_matricula
                    $course->periodo,       // para m.anio_lectivo_matricula
                    $grado,                 // para c.grado_curso
                    $letra,                 // para c.letra_curso
                    $course->periodo,       // para c.periodo_escolar

                ]);

                // confirmar transacción
                $this->commit();
                // ========================>

                
                // Flight::json($course->curso);
                // obtención del curso y número de lista asignado
                $this->array = [
                    "curso" => $course->curso,
                    "numero_lista" => $statementUpdateLetterCourse->fetch(PDO::FETCH_COLUMN),
                ];

                // retorno del curso asignado
                Flight::json($this->array);

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