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

        public function getCountGrade($periodo) {
            $this->validateToken();

            $statementCounGrade = $this->preConsult(
                "SELECT g.grado::integer, 
                CASE WHEN g.grado::integer IN (7,8) THEN 'Básico'
                WHEN g.grado::integer BETWEEN 1 AND 4 THEN 'Medio' END AS nivel,
                COALESCE(COUNT(rm.*), 0) AS count
                FROM (SELECT unnest(ARRAY['7', '8', '1', '2', '3', '4']) AS grado) g
                LEFT JOIN libromatricula.registro_matricula rm ON g.grado::integer = rm.grado AND rm.anio_lectivo_matricula = ?
                GROUP BY g.grado ORDER BY g.grado;"
            );

            try {
                $statementCounGrade->execute([intval($periodo)]);
                $countGrade = $statementCounGrade->fetchAll(PDO::FETCH_OBJ);
                foreach($countGrade as $grade) {
                    $this->array[] = [
                        "grado" => $grade->grado,
                        "nivel" => $grade->nivel,
                        "count" => $grade->count,
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

        
            
        
    }



?>