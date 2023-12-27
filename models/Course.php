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

        // public function getCountGrade($periodo) {
        //     $this->validateToken();

        //     $statementCounGrade = $this->preConsult(
        //         "SELECT g.grado::integer, 
        //         CASE WHEN g.grado::integer IN (7,8) THEN 'Básico'
        //         WHEN g.grado::integer BETWEEN 1 AND 4 THEN 'Medio' END AS nivel,
        //         COALESCE(COUNT(rm.*), 0) AS count
        //         FROM (SELECT unnest(ARRAY['7', '8', '1', '2', '3', '4']) AS grado) g
        //         LEFT JOIN libromatricula.registro_matricula rm ON g.grado::integer = rm.grado AND rm.anio_lectivo_matricula = ?
        //         GROUP BY g.grado ORDER BY g.grado;"
        //     );

        //     try {
        //         $statementCounGrade->execute([intval($periodo)]);
        //         $countGrade = $statementCounGrade->fetchAll(PDO::FETCH_OBJ);
        //         foreach($countGrade as $grade) {
        //             $this->array[] = [
        //                 "grado" => $grade->grado,
        //                 "nivel" => $grade->nivel,
        //                 "count" => $grade->count,
        //             ];
        //         }
        //         Flight::json($this->array);

        //     } catch (Exception $error) {
        //         Flight::halt(400, json_encode([
        //             "message" => "Error: ". $error->getMessage()
        //         ]));

        //     } finally {
        //         $this->closeConnection();
        //     }

            
            
        // }

        // método para obtener un listado de las matriculas y sus respectivos cursos
        public function getCourseAll($periodo) {
            // se valida el token del usuario
            $this->validateToken();

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

        public function getListCourse($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // sentencia SQL
            $statementListCourse = $this->preConsult(
                "SELECT DISTINCT letra_curso
                FROM libromatricula.registro_curso
                WHERE periodo_escolar = ?
                GROUP BY letra_curso;"
            );

            try {
                // se ejecuta la consulta
                $statementListCourse->execute([intval($periodo)]);

                // se obtiene un objeto con los datos de la consutla
                // $grades = $statementGrade->fetchAll(PDO::FETCH_OBJ);
                $listCourse = $statementListCourse->fetchAll(PDO::FETCH_COLUMN);

                // se recorre el objeto para obtener un array con todos los datos de la consulta
                // foreach($grades as $grade) {
                //     $this->array[] = [
                //         "curso" => $grade->letra_curso,
                //     ];
                // }
                $this->array = ["listCourse" => $listCourse];

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

        
            
        
    }



?>