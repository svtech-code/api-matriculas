<?php   
    namespace Models;

    use Models\Auth;
    use Models\ErrorHandler;
    use Exception;
    use Flight;
    use PDO;

    class Matricula extends Auth {
        public function __construct() {
            parent::__construct();
        }

        // Método para obtener el estado del periodo de matricula
        public function getPeriodoMatricula() {
            // sentencia SQL
            $statementPeriodoMatricula = $this->preConsult(
                "SELECT estado 
                FROM libromatricula.periodo_matricula
                WHERE anio_lectivo = EXTRACT(YEAR FROM CURRENT_DATE);"
            );

            try {
                // se ejecuta la consulta SQL
                $statementPeriodoMatricula->execute();

                // se obtiene un objeto con los datos de la consulta
                $response = $statementPeriodoMatricula->fetch(PDO::FETCH_OBJ);

                // se genera una array con los datos de la consutla
                $this->array = ["state" => $response->estado];

                // se devuelve un array con los datos de la consutla
                Flight::json($this->array);

            } catch(Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementPeriodoMatricula);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // método para obtener lista de matriculas
        public function getMatriculaAll($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // sentencia SQL
            $statmentMatricula = $this->preConsult(
                "SELECT DISTINCT 
                    m.id_registro_matricula,
                    m.numero_matricula,
                    (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                    e.apellido_paterno_estudiante,
                    e.apellido_materno_estudiante,
                    (CASE 
                        WHEN e.nombre_social_estudiante IS NULL 
                        THEN e.nombres_estudiante 
                        ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante 
                    END) AS nombres_estudiante,
                    COALESCE(to_char(e.fecha_nacimiento_estudiante, 'DD/MM/YYYY'), 'Sin registro') AS fecha_nacimiento,
                    to_char(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta,
                    to_char(m.fecha_retiro_matricula, 'DD/MM/YYYY') AS fecha_retiro,
                    to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula,
                    CASE WHEN e.sexo_estudiante = 'M' THEN 'MASCULINO' ELSE 'FEMENINO' END AS sexo, 
                    UPPER(est.estado) AS estado, 
                    m.grado, 
                    (c.grado_curso::text || c.letra_curso) AS curso,
                    (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) AS rut_apoderado_titular,
                    (apt.nombres_apoderado || ' ' || apt.apellido_paterno_apoderado || ' ' || apt.apellido_materno_apoderado) AS apoderado_titular,
                    ('+569-' || apt.telefono_apoderado) AS telefono_titular,
                    (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_apoderado_suplente,
                    (aps.nombres_apoderado || ' ' || aps.apellido_paterno_apoderado || ' ' || aps.apellido_materno_apoderado) AS apoderado_suplente,
                    ('+569-' || aps.telefono_apoderado) AS telefono_suplente,
                    CASE 
                        WHEN l.rut_estudiante IS NULL THEN true  -- Estudiante nuevo si no está en lista_sae
                        WHEN l.rut_estudiante IS NOT NULL AND l.estudiante_nuevo = true THEN true -- Si esta y es true es estudiante nuevo
                        ELSE false  -- Estudiante continuo si está en lista_sae
                    END AS estudiante_nuevo,
                    CASE 
                        WHEN m.revision_ficha IS NOT NULL THEN TRUE 
                        ELSE FALSE 
                    END AS tiene_detalle
                FROM 
                    libromatricula.registro_matricula AS m
                INNER JOIN 
                    libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN 
                    libromatricula.registro_estado AS est ON est.id_estado = m.id_estado_matricula
                LEFT JOIN 
                    libromatricula.registro_apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN 
                    libromatricula.registro_apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
                LEFT JOIN 
                    libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                LEFT JOIN 
                    (SELECT DISTINCT rut_estudiante, estudiante_nuevo
                    FROM libromatricula.lista_sae
                    WHERE periodo_matricula = ?) AS l ON l.rut_estudiante = e.rut_estudiante
                WHERE 
                    m.anio_lectivo_matricula = ?
                ORDER BY 
                    m.numero_matricula DESC;"
            );

            // respaldo de sentencia
            // $statmentMatricula = $this->preConsult(
            //     "SELECT DISTINCT m.id_registro_matricula, m.numero_matricula, (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, 
            //     e.apellido_paterno_estudiante, e.apellido_materno_estudiante, (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
            //     '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
            //     COALESCE(to_char(e.fecha_nacimiento_estudiante, 'DD/MM/YYYY'), 'Sin registro') AS fecha_nacimiento,
            //     to_char(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta,
            //     to_char(m.fecha_retiro_matricula, 'DD/MM/YYYY') AS fecha_retiro,
            //     to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula,
            //     CASE WHEN e.sexo_estudiante = 'M' THEN 'MASCULINO' ELSE 'FEMENINO' END AS sexo, UPPER(est.estado) AS estado, 
            //     m.grado, (c.grado_curso::text || c.letra_curso) AS curso,
            //     (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) as rut_apoderado_titular,
            //     (apt.nombres_apoderado || ' ' || apt.apellido_paterno_apoderado || ' ' || apt.apellido_materno_apoderado) AS apoderado_titular,
            //     ('+569-' || apt.telefono_apoderado) AS telefono_titular,
            //     (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_apoderado_suplente,
            //     (aps.nombres_apoderado || ' ' || aps.apellido_paterno_apoderado || ' ' || aps.apellido_materno_apoderado) AS apoderado_suplente,
            //     ('+569-' || aps.telefono_apoderado) AS telefono_suplente, l.estudiante_nuevo,
            //     CASE WHEN m.revision_ficha IS NOT NULL THEN TRUE ELSE FALSE END AS tiene_detalle 
            //     FROM libromatricula.registro_matricula AS m
            //     INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
            //     LEFT JOIN libromatricula.registro_estado AS est ON est.id_estado = m.id_estado_matricula
            //     LEFT JOIN libromatricula.registro_apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
            //     LEFT JOIN libromatricula.registro_apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
            //     LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
            //     LEFT JOIN libromatricula.lista_sae AS l ON l.rut_estudiante = e.rut_estudiante
            //     WHERE m.anio_lectivo_matricula = ? 
            //     ORDER BY m.numero_matricula DESC;"
            // );

            try {
                // se ejecuta la consulta
                $statmentMatricula->execute([
                    intval($periodo),
                    intval($periodo),
                ]);

                // se obtiene un objeto con los datos de la consutla
                $matriculas = $statmentMatricula->fetchAll(PDO::FETCH_OBJ);

                // se recorre el objeto para obtener un array con todos los datos de la consulta
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
                        "fecha_retiro" => $matricula->fecha_retiro,
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
                        "estudiante_nuevo" => $matricula->estudiante_nuevo,
                        "tiene_detalle" => $matricula->tiene_detalle
                    ];
                }

                // se devuelve un array con todos los datos de matricula
                Flight::json($this->array);
                
            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statmentMatricula);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // método para obtener los datos de una matricula
        public function getMatricula($id) {
            // se valida el token del usuario
            $this->validateToken();

            // sentencia SQL
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
                // se ejecuta la consulta
                $statementMatricula->execute([intval($id)]);
                
                // se obtiene un objeto con los datos de la consutla
                $matricula = $statementMatricula->fetch(PDO::FETCH_OBJ);
                
                // se obtiene un array con todos los datos de la consulta
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

                // se devuelve un array con todos los datos de matricula
                Flight::json($this->array);

            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementMatricula);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // método para obtener el número de matricula correlativo por nivel
        // tratar de eliminar método !!!!
        protected function getNumberMatricula($grade, $periodo) {
            if ($grade >= 1 && $grade <= 4) {
                // sentencia SQL
                $statementNumberMatricula = $this->preConsult(
                    "SELECT numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 1 AND 4 AND anio_lectivo_matricula = ?
                    ORDER BY numero_matricula ASC;"
                );
            } elseif ($grade >= 7 && $grade <= 8) {
                // sentencia SQL
                $statementNumberMatricula = $this->preConsult(
                    "SELECT numero_matricula
                    FROM libromatricula.registro_matricula
                    WHERE grado BETWEEN 7 AND 8 AND anio_lectivo_matricula = ?
                    ORDER BY numero_matricula ASC;"
                );
            }

            try {
                // se ejecuta la consulta SQL
                $statementNumberMatricula->execute([$periodo]);

                // se obtiene un objeto con los datos de la consulta
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

                // se retorna el número de matricula
                return $numero_matricula;


            } catch (Exception $error) {
                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            } 
            // no cerrar la conexion aún, ya que la utilizare en otro metodo
        }
        // =============================>>


        // método para registrar una matrícula
        public function setMatricula() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 4]);

            // obtención de la data enviada por el cliente
            $matricula = Flight::request()->data;

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementMatricula = $this->preConsult(
                "INSERT INTO libromatricula.registro_matricula
                (id_estudiante, id_apoderado_titular, id_apoderado_suplente,
                grado, fecha_matricula, anio_lectivo_matricula)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING numero_matricula;"
            ); 
            
            try {
                // se ejecuta la consulta
                $statementMatricula->execute([
                    intval($matricula->id_estudiante), 
                    $matricula->id_titular ? intval($matricula->id_titular): null,
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null, 
                    intval($matricula->grado),
                    $matricula->fecha_matricula,
                    intval($matricula->anio_lectivo),
                ]);

                // confirmar transacción
                $this->commit();
                // ========================>
                
                $this->array = ["numero_matricula" => $statementMatricula->fetch(PDO::FETCH_COLUMN)];
                Flight::json($this->array);

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementMatricula);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        
        // =======> desencadenar log de cambio de apoderados
        // método para actualizar una matrícula
        // al momento aún se sigue utilizando metodo antiguo para asignar nuevo numero de matricula !!!!
        // refactorizar codigo !!!!
        public function updateMatricula() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // obtención de los datos enviados desde el cliente
            $matricula = Flight::request()->data;

            // se obtiene el id_usuario del token
            $usserId = $this->getToken()->id_usuario;
            
            // obtener nivel educativo a actualizar
            $newLevel = "";
            if ($matricula->grado >= 1 && $matricula->grado <= 4) $newLevel = "Media";
            if ($matricula->grado >= 7 && $matricula->grado <= 8) $newLevel = "Basica";

            // sentencia SQL
            // comprobar cambio de nivel
            $statementCheckGrade = $this->preConsult(
                "SELECT CASE
                WHEN grado IN (7,8) THEN 'Basica'
                WHEN grado BETWEEN 1 AND 4 THEN 'Media'
                END AS nivel_educativo
                FROM libromatricula.registro_matricula
                WHERE id_registro_matricula = ?;"
            );
            
            // sentencia SQL
            // ver como manejar el numero de matricula
            $statementUpdateMatricula = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET numero_matricula = ?, id_estudiante = ?, id_apoderado_titular = ?, 
                id_apoderado_suplente = ?, grado = ?, fecha_matricula = ?,
                fecha_modificacion_matricula = CURRENT_TIMESTAMP, id_usuario_responsable = ?
                WHERE id_registro_matricula = ?;"
            );

            try {
                // se ejecuta la consulta para obtener el nivel de la matricula
                $statementCheckGrade->execute([$matricula->id_matricula]);
                $oldLevel = $statementCheckGrade->fetch(PDO::FETCH_OBJ);

                // REVISAR AQUI !! ==============================>

                // se compara los niveles y se asigna el numero de matricula
                $numero_matricula = ($newLevel === $oldLevel->nivel_educativo) 
                    ? $matricula->n_matricula
                    : $this->getNumberMatricula($matricula->grado, $matricula->anio_lectivo);


                // se ejecuta la consulta para actualizar la matricula
                $statementUpdateMatricula->execute([
                    $numero_matricula,
                    intval($matricula->id_estudiante),
                    $matricula->id_titular ? intval($matricula->id_titular) : null,
                    $matricula->id_suplente ? intval($matricula->id_suplente) : null,
                    intval($matricula->grado),
                    $matricula->fecha_matricula,
                    $usserId,
                    intval($matricula->id_matricula),
                ]);

                // se devuelve el numero de matricula
                Flight::json($numero_matricula);

                // REVISAR AQUI !! ==============================>

            } catch(Exception $error) {
                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }

        // método para registrar el retiro de una matricula
        public function putWithdrawalDateMatricula() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // se obtiene el id_usuario del token
            $usserId = $this->getToken()->id_usuario;

            // obtención de los datos enviados desde el cliente
            $matricula = Flight::request()->data;

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementWithdrawalMatricula = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET id_estado_matricula = 4, fecha_retiro_matricula = ?, fecha_baja_matricula = ?,
                id_usuario_responsable = ?, fecha_modificacion_matricula = CURRENT_TIMESTAMP
                WHERE id_registro_matricula = ?
                AND anio_lectivo_matricula = ?"
            );

            try {
                // se ejecuta la consulta
                $statementWithdrawalMatricula->execute([
                    $matricula->fechaRetiro,    // fecha de retiro de la matrícula
                    $matricula->fechaRetiro,    // fecha para la baja de matricula, misma que la del retiro
                    $usserId,                   // id del usuario responsable de la transacción
                    $matricula->idMatricula,    // id de la matricula
                    $matricula->periodo,        // periodo de la matricula
                ]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // devolución de respuesta exitosa
                $this->array = ["message" => "success"];
                Flight::json($this->array);

            } catch (Exception $error) {
                // revertir transacción en caso de error
                $this->rollBack();
                // ========================>

                // obtencuión de mensaje de error de potgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementWithdrawalMatricula);

                // excepción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError, 
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }

        }

        // función para registrar cambios manuales en ficha matricula
        public function putEditMatricula() {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 5]);

            // se obtiene el id_usuario del token
            //$usserId = $this->getToken()->id_usuario;

            // obtención de los datos enviados desde el cliente
            $dataMatricula = Flight::request()->data;

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementUpdateMatricula = $this->preConsult(
                "UPDATE libromatricula.registro_matricula
                SET revision_ficha = ?
                WHERE id_registro_matricula = ?
                AND anio_lectivo_matricula = ?"
            );

            try {

                // validación por si ya tiene detalle 
                if ($dataMatricula->tiene_detalle === true) {
                    Flight::halt(201, json_encode([
                        "message" => "Ya se ha ingresado detalle de modificación !", 
                    ]));
                }

                // se ejecuta la consulta
                $statementUpdateMatricula->execute([
                    $dataMatricula->editDetail,
                    $dataMatricula->idMatricula,
                    $dataMatricula->periodo,
                ]); 

                // confirmar transacción
                $this->commit();
                // ========================>

                // devolución de respuesta exitosa
                $this->array = ["message" => "success"];
                Flight::json($this->array);                

            } catch (Exception $error) {
                // revertir transacción en caso de error
                $this->rollBack();
                // ========================>

                // obtencuión de mensaje de error de potgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementUpdateMatricula);

                // excepción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError, 
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
            


        }

        // función para comprobar si la ficha ya ha sido descargada
        public function checkDownloadFile($id, $periodo) {
            
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 4, 5]);

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementCheckDownloadFile = $this->preConsult(
                "SELECT COUNT(*) > 0 AS has_multiple_records
                FROM libromatricula.registration_audit
                WHERE id_registration = ?
                AND periodo = ?"
            );

            try {
                // se ejecuta la consulta
                $statementCheckDownloadFile->execute([intval($id), intval($periodo)]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // se obtiene un objeto con los datos de la consulta
                $result = $statementCheckDownloadFile->fetch(PDO::FETCH_OBJ);

                $hasMultipleRecord = (bool)$result->has_multiple_records;

                // se devuelve un array con todos los datos de matricula
                Flight::json($hasMultipleRecord);


            } catch (Exception $error) {

                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementCheckDownloadFile);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }

        }


        // método para obtener estado del proceso de matrícula en curso
        // método empleado para el proceso de matricula, con la finalidad de ver estudiantes matriculados y faltantes
        public function StatusProcessMatricula($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // sentencia SQL
            $statementStatusProcessMatricula = $this->preConsult(
                "SELECT (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                l.grado_matricula, (CASE WHEN e.nombre_social_estudiante IS NULL THEN 
                e.nombres_estudiante ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END
                || ' ' || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                l.estudiante_nuevo, COUNT(m.id_registro_matricula) > 0 AS estado_matricula, 
                to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula
                FROM libromatricula.lista_sae as l
                INNER JOIN libromatricula.registro_estudiante AS e ON e.rut_estudiante = l.rut_estudiante
                LEFT JOIN libromatricula.registro_matricula AS m ON m.id_estudiante = e.id_estudiante AND m.anio_lectivo_matricula = ?
                WHERE l.periodo_matricula = ?
                GROUP BY e.id_estudiante, l.grado_matricula, m.fecha_matricula, l.estudiante_nuevo
                ORDER BY l.grado_matricula DESC;"
            );

            try {
                // se ejecuta la consulta
                $statementStatusProcessMatricula->execute([intval($periodo), intval($periodo)]);
                
                // se obtiene un objeto con los datos de la consutla
                $statusProcessMatricula = $statementStatusProcessMatricula->fetchAll(PDO::FETCH_OBJ);
                
                // se recorre el objeto para obtener un array con todos los datos de la consulta
                foreach($statusProcessMatricula as $statusProcess) {
                    $this->array[] = [
                        "rut_estudiante" => $statusProcess->rut_estudiante,
                        "grado_matricula" => $statusProcess->grado_matricula,
                        "nombres_estudiante" => $statusProcess->nombres_estudiante,
                        "estudiante_nuevo" => $statusProcess->estudiante_nuevo,
                        "estado_matricula" => $statusProcess->estado_matricula,
                        "fecha_matricula" => $statusProcess->fecha_matricula,
                    ];
                }

                // se devuelve un array con todos los datos de matricula
                Flight::json($this->array);

            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementStatusProcessMatricula);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }
        }


        // ======= metodo eliminado por funcionalidad en base de datos !!
        // método para comprobar si un estudiante ya se encuentra matriculado
        // protected function verifStudentMatricula($id_estudiante, $periodo) {
        //     // sentencia SQL
        //     $statementVerifyStudent = $this->preConsult(
        //         "SELECT id_registro_matricula
        //         FROM libromatricula.registro_matricula
        //         WHERE id_estudiante = ? AND anio_lectivo_matricula = ?"
        //     );

        //     try {
        //         // se ejecuta la consulta SQL
        //         $statementVerifyStudent->execute([intval($id_estudiante), intval($periodo)]);
                
        //         // se obtiene un objeto con los datos de la consulta
        //         $verify = $statementVerifyStudent->fetch(PDO::FETCH_OBJ);

        //         // condición para verificar si el estudiante ya se encuentra matriculado
        //         if ($verify) {
        //             throw new Exception("El estudiante ya se encuentra matriculado", 409);
        //         }

        //     } catch (Exception $error) {
        //         // obtención del codigo de error
        //         $statusCode = $error->getCode() ?: 404;

        //         // expeción personalizada para errores
        //         Flight::halt($statusCode, json_encode([
        //             "message" => "Error: ". $error->getMessage(),
        //         ]));
        //     }
        // }


        
    }



    



?>
