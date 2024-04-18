<?php
    namespace Models;

    use DateTime;
    use DateTimeZone;
    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;
    use PhpOffice\PhpWord\TemplateProcessor;
    use PhpOffice\PhpWord\Settings;
    use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};
    use PhpOffice\PhpSpreadsheet\Style\{Fill, Border};
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    class Report extends Auth {
        private $tempDir = './document';
        private $currentMonth = '';
        private $styleTitle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'FFCCCCCC', // color gris
                ],
            ],
            'font' => [
                'bold' => true,
                'size' => 13,
            ],
        ];
        private $styleRetired = [
            'font' => [
                'strikethrough' => true,
                'bold' => true,
                'color' => [
                    'argb' => 'FF0000', // color rojo
                ],
            ],
        ];
        private $styleWithdrawal = [
            'font' => [
                'strikethrough' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'DDDDDD', // Código de color para gris claro
                ],
            ]
        ];
        private $styleOrange = [
            'font' => [
                // 'strikethrough' => true,
                'bold' => true,
                'color' => [
                    'argb' => 'FFA500', // color rojo
                ],
            ],
        ];
        private $month = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre'
        ];

        public function __construct() {
            parent::__construct();
            // se genera la carpeta temporal con los permisos correspondientes dentro del servidor
            if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);
            Settings::setTempDir($this->tempDir);
            $this->currentMonth = date('F');
        }

        // method to create excel object
        private function createExcelObject($title) {
            // se crea un objeto libro de excel
            $file = new Spreadsheet();
            
            // se aplican algunas propiedades al objeto libro de excel
            $file
                ->getProperties()
                ->setCreator('Dpto. Informátice')
                ->setLastModifiedBy('Informática')
                ->setTitle($title);

            return $file;
        }

        // method to download excel file
        private function downloadExcelFile($file, $title, $period) {
            // cabeceras de la descarga
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'. $title. '_'. $period. 'xlsx"');
            header('Cache-Control: max-age=0');

            // se genera el archivo excel
            $writer = IOFactory::createWriter($file, 'Xlsx');
            $writer->save('php://output');
        }

        // method to generate registration certificate
        public function getCertificadoMatricula($rut, $periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReport = $this->preConsult(
                "SELECT 
                (e.nombres_estudiante || ' ' || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, m.grado,
                CASE WHEN m.grado IN (7,8) THEN 'Básica' WHEN m.grado BETWEEN 1 AND 4 THEN 'Media' END AS nivel,
                m.anio_lectivo_matricula, m.numero_matricula
                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                WHERE e.rut_estudiante = ? AND m.anio_lectivo_matricula = ?;"
            );

            try {
                // SQL query execution
                $statementReport->execute([$rut, intval($periodo)]);

                // confirm transaction
                $this->commit();
                // ========================>

                // obtaining object with query data
                $report = $statementReport->fetch(PDO::FETCH_OBJ);

                // routes for word templates
                $templateCertificadoMatricula = './document/certificadoMatricula.docx';
                $templateCertificadoMatriculaTemp = './document/certificadoMatricula_temp.docx';
    
                // create a templateProcessor object
                $file = new TemplateProcessor($templateCertificadoMatricula);
    
                // dynamic data assignment
                $file->setValues(
                    [
                        'nombre' => $report->nombres_estudiante,
                        'rut' => $report->rut_estudiante,
                        'grado' => $report->grado,
                        'nivel' => $report->nivel,
                        'anio_1' => $report->anio_lectivo_matricula,
                        'matricula' => $report->numero_matricula,
                        'mes' => $this->month[$this->currentMonth],
                        'dia' => date('j'),
                        'anio_2' => date('Y'),
                    ]
                );
    
                // save modified word template
                $file->saveAs($templateCertificadoMatriculaTemp);
    
                // download degenerated word document
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header("Content-Disposition: attachment; filename=$templateCertificadoMatriculaTemp");
                readfile($templateCertificadoMatriculaTemp);
                unlink($templateCertificadoMatriculaTemp);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReport);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));
            
            } finally {
                // closing the connection with the database
                $this->closeConnection();
            }
        }

        // metodh to generate regular student certificate
        public function getCertificadoAlumnoRegular($rut, $periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReport = $this->preConsult(
                "SELECT (e.nombres_estudiante || ' ' || e.apellido_paterno_estudiante 
                || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, m.grado,
                CASE WHEN m.grado IN (7,8) THEN 'Básica' WHEN m.grado BETWEEN 1 AND 4 THEN 'Media' END AS nivel,
                m.anio_lectivo_matricula, m.numero_matricula, c.letra_curso
                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e on e.id_estudiante = m.id_estudiante
                INNER JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                WHERE e.rut_estudiante = ? AND m.anio_lectivo_matricula = ?;"
            );

            try {
                // SQL query execution
                $statementReport->execute([$rut, intval($periodo)]);

                // confirm transaction
                $this->commit();
                // ========================>

                // obtaining object with query data
                $report = $statementReport->fetch(PDO::FETCH_OBJ);
                
                // exception for enrollment without an assidned course
                if (!$report) {
                    throw new Exception("Matrícula sin curso asignado", 409);
                }

                // routes for word templates
                $templateCertificadoAlumnoRegular = './document/certificadoAlumnoRegular.docx';
                $templateCertificadoAlumnoRegularTemp = './document/certificadoAlumnoRegular_temp.docx';
    
                // create a templateProcessor object
                $file = new TemplateProcessor($templateCertificadoAlumnoRegular);
    
                // dynamic data assignment
                $file->setValues(
                    [
                        'nombre' => $report->nombres_estudiante,
                        'rut' => $report->rut_estudiante,
                        'grado' => $report->grado,
                        'letra' => $report->letra_curso,
                        'nivel' => $report->nivel,
                        'anio_1' => $report->anio_lectivo_matricula,
                        'matricula' => $report->numero_matricula,
                        'mes' => $this->month[$this->currentMonth],
                        'dia' => date('j'),
                        'anio_2' => date('Y'),
                    ]
                );
    
                // save modified word template
                $file->saveAs($templateCertificadoAlumnoRegularTemp);
    
                // download degenerated word document
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header("Content-Disposition: attachment; filename=$templateCertificadoAlumnoRegularTemp");
                readfile($templateCertificadoAlumnoRegularTemp);
                unlink($templateCertificadoAlumnoRegularTemp);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReport);

                // custom exception for errors
                $statusCode = $error->getCode() ? $error->getCode() : 404;

                // expeción personalizada para errores
                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // closing the connection with the database
                $this->closeConnection();
            }

        }

        //method to generate registration report
        public function getReportMatricula($dateFrom, $dateTo, $periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2, 3, 4]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReportMatricula = $this->preConsult(
                "SELECT m.numero_matricula, m.grado, (c.grado_curso || c.letra_curso) AS curso,
                m.fecha_matricula, m.fecha_alta_matricula,
                (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                e.apellido_paterno_estudiante, e.apellido_materno_estudiante, e.nombres_estudiante,
                e.nombre_social_estudiante, e.fecha_nacimiento_estudiante, e.sexo_estudiante,

                (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) AS rut_titular,
                (apt.apellido_paterno_apoderado || ' ' || apt.apellido_materno_apoderado || ' '
                || apt.nombres_apoderado) AS nombres_titular, apt.telefono_apoderado AS telefono_titular, 
                apt.direccion_apoderado AS direccion_titular, 
                    
                (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_suplente,
                (aps.apellido_paterno_apoderado || ' ' || aps.apellido_materno_apoderado || ''
                || aps.nombres_apoderado) AS nombres_suplente, aps.telefono_apoderado AS telefono_suplente,
                aps.direccion_apoderado AS direccion_suplente	

                FROM libromatricula.registro_matricula AS m
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                LEFT JOIN libromatricula.registro_apoderado AS apt ON apt.id_apoderado = m.id_apoderado_titular
                LEFT JOIN libromatricula.registro_apoderado AS aps ON aps.id_apoderado = m.id_apoderado_suplente
                LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                WHERE m.anio_lectivo_matricula = ?
                AND m.fecha_registro_matricula >= ? AND m.fecha_registro_matricula <= ?
                ORDER BY e.apellido_paterno_estudiante ASC;"
            );

            try {
                // SQL query execution
                $statementReportMatricula->execute([intval($periodo), $dateFrom, $dateTo]);

                // confirm transaction
                $this->commit();
                // ========================>
                
                // obtaining object with query data
                $reportMatricula = $statementReportMatricula->fetchAll(PDO::FETCH_OBJ);

                // create a excel object
                $file = $this->createExcelObject("Registro Matrícula");

                // selection and modification of the main sheet
                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro de matrículas");
                $sheetActive->setShowGridLines(false);
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // excel sheet title
                $sheetActive->setCellValue('A1', 'Registro de matrículas periodo '. $periodo);
                
                // applying filter on headers
                $sheetActive->setAutoFilter('A3:T3');

                // application of styles on headers
                $sheetActive->getStyle('A3:T3')->applyFromArray($this->styleTitle);

                // view lock for headers
                $sheetActive->freezePane('A4');

                // cell width
                $sheetActive->getColumnDimension('A')->setWidth(15);
                $sheetActive->getColumnDimension('B')->setWidth(20);
                $sheetActive->getColumnDimension('C')->setWidth(15);
                $sheetActive->getColumnDimension('D')->setWidth(10);
                $sheetActive->getColumnDimension('E')->setWidth(10);
                $sheetActive->getColumnDimension('F')->setWidth(20);
                $sheetActive->getColumnDimension('G')->setWidth(24);
                $sheetActive->getColumnDimension('H')->setWidth(24);
                $sheetActive->getColumnDimension('I')->setWidth(40);
                $sheetActive->getColumnDimension('J')->setWidth(20);
                $sheetActive->getColumnDimension('K')->setWidth(20);
                $sheetActive->getColumnDimension('L')->setWidth(18);

                $sheetActive->getColumnDimension('M')->setWidth(16);
                $sheetActive->getColumnDimension('N')->setWidth(40);
                $sheetActive->getColumnDimension('O')->setWidth(22);
                $sheetActive->getColumnDimension('P')->setWidth(60);

                $sheetActive->getColumnDimension('Q')->setWidth(16);
                $sheetActive->getColumnDimension('R')->setWidth(40);
                $sheetActive->getColumnDimension('S')->setWidth(22);
                $sheetActive->getColumnDimension('T')->setWidth(60);


                // cell content alignment
                $sheetActive->getStyle('A:E')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('K:L')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('O')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('S')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left');                
                $sheetActive->getStyle('A3:T3')->getAlignment()->setHorizontal('left');                

                // header titles
                $sheetActive->setCellValue('A3', 'MATRICULA');
                $sheetActive->setCellValue('B3', 'FECHA_MATRICULA');
                $sheetActive->setCellValue('C3', 'FECHA_ALTA');
                $sheetActive->setCellValue('D3', 'GRADO');
                $sheetActive->setCellValue('E3', 'CURSO');
                $sheetActive->setCellValue('F3', 'RUT_ESTUDIANTE');
                $sheetActive->setCellValue('G3', 'APELLIDO_PATERNO');
                $sheetActive->setCellValue('H3', 'APELLIDO_MATERNO');
                $sheetActive->setCellValue('I3', 'NOMBRES_ESTUDIANTE');
                $sheetActive->setCellValue('J3', 'NOMBRE_SOCIAL');
                $sheetActive->setCellValue('K3', 'FECHA_NACIMIENTO');
                $sheetActive->setCellValue('L3', 'SEXO_ESTUDIANTE');

                $sheetActive->setCellValue('M3', 'RUT_TITULAR');
                $sheetActive->setCellValue('N3', 'NOMBRES_TITULAR');
                $sheetActive->setCellValue('O3', 'TELEFONO_TITULAR');
                $sheetActive->setCellValue('P3', 'DIRECCOIN_TITULAR');

                $sheetActive->setCellValue('Q3', 'RUT_SUPLENTE');
                $sheetActive->setCellValue('R3', 'NOMBRES_SUPLENTE');
                $sheetActive->setCellValue('S3', 'TELEFONO_SUPLENTE');
                $sheetActive->setCellValue('T3', 'DIRECCION_SUPLENTE');

                // main writing row
                $fila = 4;

                // traversal of data object to insert into rows (recorrido del objeto de datos para insertar en filas)
                foreach ($reportMatricula as $report) {
                    $sheetActive->setCellValue('A'.$fila, $report->numero_matricula);
                    $sheetActive->setCellValue('B'.$fila, $report->fecha_matricula);
                    $sheetActive->setCellValue('C'.$fila, $report->fecha_alta_matricula);
                    $sheetActive->setCellValue('D'.$fila, $report->grado);
                    $sheetActive->setCellValue('E'.$fila, $report->curso);
                    $sheetActive->setCellValue('F'.$fila, $report->rut_estudiante);
                    $sheetActive->setCellValue('G'.$fila, $report->apellido_paterno_estudiante);
                    $sheetActive->setCellValue('H'.$fila, $report->apellido_materno_estudiante);
                    $sheetActive->setCellValue('I'.$fila, $report->nombres_estudiante);
                    $sheetActive->setCellValue('J'.$fila, $report->nombre_social_estudiante);
                    $sheetActive->setCellValue('K'.$fila, $report->fecha_nacimiento_estudiante);
                    $sheetActive->setCellValue('L'.$fila, $report->sexo_estudiante);

                    $sheetActive->setCellValue('M'.$fila, $report->rut_titular);
                    $sheetActive->setCellValue('N'.$fila, $report->nombres_titular);
                    $sheetActive->setCellValue('O'.$fila, $report->telefono_titular ? '+569-'. $report->telefono_titular : $report->telefono_titular);
                    $sheetActive->setCellValue('P'.$fila, $report->direccion_titular);

                    $sheetActive->setCellValue('Q'.$fila, $report->rut_suplente);
                    $sheetActive->setCellValue('R'.$fila, $report->nombres_suplente);
                    $sheetActive->setCellValue('S'.$fila, $report->telefono_suplente ? '+569-'. $report->telefono_suplente : $report->telefono_suplente);
                    $sheetActive->setCellValue('T'.$fila, $report->direccion_suplente);

                    $fila++;
                }

                // excel file download ========================>
                $this->downloadExcelFile($file, "ReporteMatricula_", $periodo);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReportMatricula);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // closing the connection with the database
                $this->closeConnection();
            }

        }

        // method to generate course report
        public function getReportCourses($periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            // $this->validatePrivilege([1, 2, 3, 4]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReportCourses = $this->preConsult(
                "SELECT 
                    to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula,
                    m.numero_matricula, 
                    (c.grado_curso || '' || c.letra_curso) AS curso, 
                    m.numero_lista_curso AS n_lista,
                    to_char(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta_matricula,
                    NULL AS fecha_baja_matricula,
                    to_char(m.fecha_retiro_matricula, 'DD/MM/YYYY') AS fecha_retiro_matricula,
                    e.sexo_estudiante, e.apellido_paterno_estudiante, e.apellido_materno_estudiante,
                    (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
                    '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
                    (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, ee.estado AS estado_estudiante
                FROM libromatricula.registro_matricula AS m
                    LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                    LEFT JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                    INNER JOIN libromatricula.registro_estado AS ee ON ee.id_estado = m.id_estado_matricula
                WHERE m.anio_lectivo_matricula = ? and m.id_estado_matricula <> 4
                
                UNION ALL
                
                SELECT
                    to_char(m.fecha_matricula, 'DD/MM/YYYY') AS fecha_matricula,
                    m.numero_matricula,
                    (c.grado_curso || '' || c.letra_curso) AS curso,
                    log.old_number_list AS n_lista,
                    to_char(log.discharge_date, 'DD/MM/YYYY') AS fecha_alta_matricula,
                    to_char(log.withdrawal_date, 'DD/MM/YYYY') AS fecha_baja_matricula,
                    to_char(m.fecha_retiro_matricula, 'DD/MM/YYYY') AS fecha_retiro_matricula,
                    e.sexo_estudiante, e.apellido_paterno_estudiante, e.apellido_materno_estudiante,
                    (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
                    '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
                    (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, ee.estado AS estado_estudiante
                FROM libromatricula.student_withdrawal_from_list_log AS log
                    LEFT JOIN libromatricula.registro_matricula AS m ON m.id_registro_matricula = log.id_registro_matricula
                    LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = log.id_old_course
                    LEFT JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                    INNER JOIN libromatricula.registro_estado AS ee ON ee.id_estado = m.id_estado_matricula
                WHERE m.anio_lectivo_matricula = ?"
            );

            try {
                // SQL query execution
                $statementReportCourses->execute([intval($periodo), intval($periodo)]);

                // confirm transaction
                $this->commit();
                // ========================>

                // obtaining object with query data
                $reportCourses = $statementReportCourses->fetchAll(PDO::FETCH_OBJ);

                // create a excel object
                $file = $this->createExcelObject("Registro cursos");

                // selection and modification of the main sheet
                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro de cursos");
                $sheetActive->setShowGridLines(false);                
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // excel sheet title
                $sheetActive->setCellValue('A1', 'Registro de cursos periodo '. $periodo);

                // applying filter on headers
                $sheetActive->setAutoFilter('A3:M3');

                // application of styles on headers
                $sheetActive->getStyle('A3:M3')->applyFromArray($this->styleTitle);

                // view lock for headers
                $sheetActive->freezePane('A4');

                // cell width
                $sheetActive->getColumnDimension('A')->setWidth(20);
                $sheetActive->getColumnDimension('B')->setWidth(18);
                $sheetActive->getColumnDimension('C')->setWidth(10);
                $sheetActive->getColumnDimension('D')->setWidth(10);
                $sheetActive->getColumnDimension('E')->setWidth(16);
                $sheetActive->getColumnDimension('F')->setWidth(16);
                $sheetActive->getColumnDimension('G')->setWidth(16);
                $sheetActive->getColumnDimension('H')->setWidth(10);
                $sheetActive->getColumnDimension('I')->setWidth(22);
                $sheetActive->getColumnDimension('J')->setWidth(22);
                $sheetActive->getColumnDimension('K')->setWidth(30);
                $sheetActive->getColumnDimension('L')->setWidth(20);
                $sheetActive->getColumnDimension('M')->setWidth(24);


                // cell content alignment
                $sheetActive->getStyle('A:H')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('L')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left'); 
                $sheetActive->getStyle('A3:M3')->getAlignment()->setHorizontal('left'); 

                // header titles
                $sheetActive->setCellValue('A3', 'FECHA MATRICULA');
                $sheetActive->setCellValue('B3', 'Nº MATRICULA');
                $sheetActive->setCellValue('C3', 'CURSO');
                $sheetActive->setCellValue('D3', 'N LISTA');
                $sheetActive->setCellValue('E3', 'FECHA ALTA');
                $sheetActive->setCellValue('F3', 'FECHA BAJA');
                $sheetActive->setCellValue('G3', 'FECHA RETIRO');
                $sheetActive->setCellValue('H3', 'SEXO');
                $sheetActive->setCellValue('I3', 'APELLIDO PATERNO');
                $sheetActive->setCellValue('J3', 'APELLIDO MATERNO');
                $sheetActive->setCellValue('K3', 'NOMBRES');
                $sheetActive->setCellValue('L3', 'RUT ESTUDIANTE');
                $sheetActive->setCellValue('M3', 'ESTADO ESTUDIANTE');

                // main writing row
                $fila = 4;

                // traversal of data object to insert into rows (recorrido del objeto de datos para insertar en filas)
                foreach ($reportCourses as $course) {
                    $sheetActive->setCellValue('A'.$fila, $course->fecha_matricula);
                    $sheetActive->setCellValue('B'.$fila, $course->numero_matricula ? $course->numero_matricula: "N/A");
                    $sheetActive->setCellValue('C'.$fila, $course->curso);
                    $sheetActive->setCellValue('D'.$fila, $course->n_lista ? $course->n_lista : "N/A");
                    $sheetActive->setCellValue('E'.$fila, $course->fecha_alta_matricula);
                    $sheetActive->setCellValue('F'.$fila, $course->fecha_baja_matricula);
                    $sheetActive->setCellValue('G'.$fila, $course->fecha_retiro_matricula);
                    $sheetActive->setCellValue('H'.$fila, $course->sexo_estudiante);
                    $sheetActive->setCellValue('I'.$fila, $course->apellido_paterno_estudiante);
                    $sheetActive->setCellValue('J'.$fila, $course->apellido_materno_estudiante);
                    $sheetActive->setCellValue('K'.$fila, $course->nombres_estudiante);
                    $sheetActive->setCellValue('L'.$fila, $course->rut_estudiante);
                    $sheetActive->setCellValue('M'.$fila, $course->estado_estudiante);

                    // apply red highlight style for withdrawals (aplicar estilo resaltado rojo para retiros)
                    if ($course->estado_estudiante === 'Retirado (a)') {
                        $sheetActive->getStyle('A'.$fila.':M'.$fila)->applyFromArray($this->styleRetired);
                    }

                    // apply orange highlight style for suspension (aplicar estilo resaltado naranjo para suspención)
                    if ($course->estado_estudiante === 'Suspendido (a)') {
                        $sheetActive->getStyle('A'.$fila.':M'.$fila)->applyFromArray($this->styleOrange);
                    }

                    // apply gray highlight style for lows (aplicar estilo resaltado gris para bajas)
                    if ($course->estado_estudiante === 'Matriculado (a)' && $course->fecha_baja_matricula !== null) {
                        $sheetActive->getStyle('A'.$fila.':M'.$fila)->applyFromArray($this->styleWithdrawal);
                    }
                    
                    $fila++;
                }

                // excel file download ========================>
                $this->downloadExcelFile($file, "ReporteCursos_", $periodo);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReportCourses);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // closing the connection with the database
                $this->closeConnection();
            }
        }

        // method to generate course payroll
        public function getReportCourse($periodo, $course) {
           // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2, 4]);

            // separate letter and grade management
            $grado = substr($course, 0, 1);
            $letra = substr($course, 1, 1);
            
            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReportCourseLetter = $this->preConsult(
                "SELECT 
                    numero_lista,
                    numero_matricula,
                    fecha_alta_matricula,
                    fecha_baja_matricula,
                    sexo_estudiante,
                    apellido_paterno_estudiante,
                    apellido_materno_estudiante,
                    nombres_estudiante,
                    rut_estudiante,
                    dj.nombres_funcionario || ' ' || dj.apellido_paterno_funcionario || ' ' || dj.apellido_materno_funcionario AS docente_jefe,
                    ig.nombres_funcionario || ' ' || ig.apellido_paterno_funcionario || ' ' || ig.apellido_materno_funcionario AS inspector_general,
                    p.nombres_funcionario || ' ' || p.apellido_paterno_funcionario || ' ' || p.apellido_materno_funcionario AS paradocente
                FROM (
                    -- consulta de registros y retiros de matricula
                    SELECT			
                        CASE
                            WHEN p.autocorrelativo_listas THEN
                                ROW_NUMBER() OVER(
                                    ORDER BY
                                        unaccent(e.apellido_paterno_estudiante),
                                        unaccent(e.apellido_materno_estudiante),
                                        unaccent(e.nombres_estudiante)
                                )
                            ELSE
                                m.numero_lista_curso
                        END AS numero_lista,
                        m.numero_matricula,
                        TO_CHAR(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta_matricula,
                        TO_CHAR(m.fecha_retiro_matricula, 'DD/MM/YYYY') AS fecha_baja_matricula,
                        CASE
                            WHEN e.sexo_estudiante = 'M' THEN 1 ELSE 2 END AS sexo_estudiante,
                        e.apellido_paterno_estudiante, 
                        e.apellido_materno_estudiante,
                        CASE 
                            WHEN e.nombre_social_estudiante IS NULL THEN 
                                e.nombres_estudiante 
                            ELSE 
                                '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END AS nombres_estudiante,
                        (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante
                    
                    FROM 
                        libromatricula.registro_matricula AS m
                        INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                        INNER JOIN libromatricula.periodo_matricula AS p ON p.anio_lectivo = ?
                        INNER JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                    WHERE
                        m.anio_lectivo_matricula = ?
                        AND m.id_estado_matricula <> 4
                        AND c.grado_curso = ?
                        AND c.letra_curso = ?
                        AND c.periodo_escolar = ?

                    UNION ALL
                    
                    -- consulta hacia log de retiros y cambios
                    SELECT 
                        l.old_number_list AS numero_lista,
                        m.numero_matricula,
                        TO_CHAR(l.discharge_date, 'DD/MM/YYYY') AS fecha_alta_matricula,
                        

                    
                        (TO_CHAR(l.withdrawal_date, 'DD/MM/YYYY') || ' (' || 
                        CASE 
                            WHEN m.id_estado_matricula = 4 THEN 'R' 
                            ELSE (c.grado_curso || c.letra_curso) --seguir aqui 
                        END || ')') AS fecha_baja_matricula,
                        CASE
                            WHEN e.sexo_estudiante = 'M' THEN 1 ELSE 2 END AS sexo_estudiante,
                        e.apellido_paterno_estudiante, 
                        e.apellido_materno_estudiante,
                        CASE 
                            WHEN e.nombre_social_estudiante IS NULL THEN 
                                e.nombres_estudiante 
                            ELSE 
                                '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END AS nombres_estudiante,
                        (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante
                    FROM 
                        libromatricula.student_withdrawal_from_list_log AS l
                        INNER JOIN libromatricula.registro_matricula AS m ON m.id_registro_matricula = l.id_registro_matricula
                        INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                        INNER JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                    WHERE
                        m.anio_lectivo_matricula = ?
                        AND l.id_old_course = (
                            SELECT id_curso
                            FROM libromatricula.registro_curso
                            WHERE grado_curso = ? AND letra_curso = ? AND periodo_escolar = ?
                        )
                ) AS combined_data
                    INNER JOIN libromatricula.registro_curso AS c ON c.id_curso = (
                        SELECT id_curso FROM libromatricula.registro_curso WHERE grado_curso = ? AND letra_curso = ? AND periodo_escolar = ?
                    )
                    LEFT JOIN libromatricula.registro_funcionario AS dj ON dj.id_funcionario = c.id_docente_jefe
                    LEFT JOIN libromatricula.registro_funcionario AS ig ON ig.id_funcionario = c.id_inspectoria_general
                    LEFT JOIN libromatricula.registro_funcionario AS p ON p.id_funcionario = c.id_paradocente
                    
                GROUP BY 
                    numero_lista,
                    numero_matricula,
                    fecha_alta_matricula,
                    fecha_baja_matricula,
                    sexo_estudiante,
                    apellido_paterno_estudiante,
                    apellido_materno_estudiante,
                    nombres_estudiante,
                    rut_estudiante,
                    docente_jefe,
                    inspector_general,
                    paradocente
                    
                ORDER BY
                    numero_lista;"
            );

            try {
                // SQL query execution
                $statementReportCourseLetter->execute([
                    intval($periodo),       // para p.anio_lectivo
                    intval($periodo),       // para m.anio_lectivo_matricula
                    intval($grado),         // para c.grado_curso
                    $letra,                 // para c.letra_curso
                    intval($periodo),       // para c.periodo_escolar

                    intval($periodo),       // para m.anio_lectivo_matricula
                    intval($grado),         // para c.grado_curso
                    $letra,                 // para c.letra_curso
                    intval($periodo),       // para c.periodo_escolar

                    intval($grado),         // para grado_curso
                    $letra,                 // para letra_curso
                    intval($periodo),       // para periodo_escolar
                ]);

                // confirm transaction
                $this->commit();
                // ========================>

                // obtaining object with query data
                $reportCourseLetter = $statementReportCourseLetter->fetchAll(PDO::FETCH_OBJ);

                // load template
                $file = IOFactory::load("./document/nomina_curso.xlsx");

                // get active spreedsheet
                $sheetActive = $file->getActiveSheet();

                // assign title to the sheet
                $sheetActive->setTitle("Nómina ". $course);

                // payroll title
                $sheetActive->setCellValue('C3', "NÓMINA PARA REGISTRO DE ASISTENCIA DIARIA ". $periodo);

                // course asignment
                $sheetActive->setCellValue('I6', $course);

                // course officials
                $docenteJefe = "";
                $inspectorGeneral = "";
                $paradocente = "";

                // counter for student gender
                $countMale = 0;
                $countFemale = 0;
                $countTotal = 0;

                // main writing row
                $fila = 12;

                // traversal of data object to insert into rows (recorrido del objeto de datos para insertar en filas)
                foreach ($reportCourseLetter as $courseLetter) {
                    $sheetActive->setCellValue('A'.$fila, $courseLetter->numero_lista);
                    $sheetActive->setCellValue('B'.$fila, $courseLetter->numero_matricula);
                    $sheetActive->setCellValue('C'.$fila, $courseLetter->fecha_alta_matricula);
                    $sheetActive->setCellValue('D'.$fila, $courseLetter->fecha_baja_matricula);
                    $sheetActive->setCellValue('E'.$fila, $courseLetter->sexo_estudiante);
                    $sheetActive->setCellValue('F'.$fila, $courseLetter->apellido_paterno_estudiante);
                    $sheetActive->setCellValue('G'.$fila, $courseLetter->apellido_materno_estudiante);
                    $sheetActive->setCellValue('H'.$fila, $courseLetter->nombres_estudiante);
                    $sheetActive->setCellValue('I'.$fila, $courseLetter->rut_estudiante);

                    // apply red highlight style for withdrawals (aplicar estilo resaltado rojo para retiros)
                    if ($courseLetter->fecha_baja_matricula) {
                        $sheetActive->getStyle('A'.$fila.':I'.$fila)->applyFromArray($this->styleWithdrawal);
                    }

                    // obtain officials responsible for the course
                    if (empty($docenteJefe)) $docenteJefe = $courseLetter->docente_jefe;
                    if (empty($inspectorGeneral)) $inspectorGeneral = $courseLetter->inspector_general;
                    if (empty($paradocente)) $paradocente = $courseLetter->paradocente;

                    // student gender counter and total
                    if ($courseLetter->sexo_estudiante === 1 && !$courseLetter->fecha_baja_matricula) $countMale++;
                    if ($courseLetter->sexo_estudiante === 2 && !$courseLetter->fecha_baja_matricula) $countFemale++;
                    if (!$courseLetter->fecha_baja_matricula) $countTotal++;
                    
                    $fila++;
                }

                // assugnment of the obtained variables 
                $sheetActive->setCellValue('F6', $docenteJefe);
                $sheetActive->setCellValue('F7', $inspectorGeneral);
                $sheetActive->setCellValue('F8', $paradocente);

                $sheetActive->setCellValue('F58', $countMale);
                $sheetActive->setCellValue('F59', $countFemale);
                $sheetActive->setCellValue('F60', $countTotal);

                // set local time zone
                $zonaHorariaChile = new DateTimeZone('America/Santiago');
                
                // create object with local timezone
                $horaActualChile = new DateTime('now', $zonaHorariaChile);
                
                // set timezone in payroll
                $sheetActive->setCellValue('I61', $horaActualChile->format('d-m-Y H:i:s'));

                // setting http headers for file download
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="Nómina_2024.xlsx"');
                header('Cache-Control: max-age=0');

                // generate file
                $writer = new Xlsx($file);

                // download excel file
                $writer->save('php://output');

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReportCourseLetter);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                 // closing the connection with the database
                $this->closeConnection();
            }
        }

        // method to generate registration process report
        public function getReportProcessMatricula($periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2, 4]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReportProcessMatricula = $this->preConsult(
                "SELECT m.numero_matricula, (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                l.grado_matricula, (CASE WHEN e.nombre_social_estudiante IS NULL THEN 
                e.nombres_estudiante ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END
                || ' ' || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                l.estudiante_nuevo, COUNT(m.id_registro_matricula) > 0 AS estado_matricula, m.fecha_matricula
                FROM libromatricula.lista_sae as l
                INNER JOIN libromatricula.registro_estudiante AS e ON e.rut_estudiante = l.rut_estudiante
                LEFT JOIN libromatricula.registro_matricula AS m ON m.id_estudiante = e.id_estudiante AND m.anio_lectivo_matricula = ?
                WHERE l.periodo_matricula = ?
                GROUP BY e.id_estudiante, l.grado_matricula, m.fecha_matricula, l.estudiante_nuevo, m.numero_matricula
                ORDER BY l.grado_matricula DESC;"
            );

            try {
                // SQL query execution
                $statementReportProcessMatricula->execute([intval($periodo), intval($periodo)]);
                
                // confirm transaction
                $this->commit();
                // ========================>
                
                // obtaining object with query data
                $reportProcessMatricula = $statementReportProcessMatricula->fetchAll(PDO::FETCH_OBJ);

                // create a excel object
                $file = $this->createExcelObject("Registro proceso matrícula");

                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle('Registro proceso matrícula');
                $sheetActive->setShowGridlines(false);
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // excel sheet title
                $sheetActive->setCellValue('A1', 'Registro Proceso matrícula periodo '. $periodo);
                
                // applying filter on headers
                $sheetActive->setAutoFilter('A3:G3'); 
                
                // application of styles on headers
                $sheetActive->getStyle('A3:G3')->getFont()->setBold(true)->setSize(12);

                // view lock for headers
                $sheetActive->freezePane('A4');

                // cell width
                $sheetActive->getColumnDimension('A')->setWidth(15);
                $sheetActive->getColumnDimension('B')->setWidth(20);
                $sheetActive->getColumnDimension('C')->setWidth(10);
                $sheetActive->getColumnDimension('D')->setWidth(40);
                $sheetActive->getColumnDimension('E')->setWidth(20);
                $sheetActive->getColumnDimension('F')->setWidth(20);
                $sheetActive->getColumnDimension('G')->setWidth(20);

                // cell content alignment
                $sheetActive->getStyle('A:C')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('E:G')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left');

                // header titles
                $sheetActive->setCellValue('A3', 'MATRICULA');
                $sheetActive->setCellValue('B3', 'RUT');
                $sheetActive->setCellValue('C3', 'GRADO');
                $sheetActive->setCellValue('D3', 'NOMBRES ESTUDIANTE');
                $sheetActive->setCellValue('E3', 'TIPO');
                $sheetActive->setCellValue('F3', 'ESTADO');
                $sheetActive->setCellValue('G3', 'FECHA MATRICULA');

                // main writing row
                $fila = 4;

                // traversal of data object to insert into rows (recorrido del objeto de datos para insertar en filas)
                foreach($reportProcessMatricula as $processMatricula) {
                    $sheetActive->setCellValue('A'.$fila, $processMatricula->numero_matricula);
                    $sheetActive->setCellValue('B'.$fila, $processMatricula->rut_estudiante);
                    $sheetActive->setCellValue('C'.$fila, $processMatricula->grado_matricula);
                    $sheetActive->setCellValue('D'.$fila, $processMatricula->nombres_estudiante);
                    $sheetActive->setCellValue('E'.$fila, $processMatricula->estudiante_nuevo === true ? "NUEVO" : "CONTINUA");
                    $sheetActive->setCellValue('F'.$fila, $processMatricula->estado_matricula === true ? "MATRICULADO" : "NO MATRICULADO");
                    $sheetActive->setCellValue('G'.$fila, $processMatricula->fecha_matricula);
                    
                    $fila++;
                }

                // excel file download ========================>
                $this->downloadExcelFile($file, "ReporteMatricula_", $periodo);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                 // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReportProcessMatricula);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // closing the connection with the database
                $this->closeConnection();
            }
        }

        // method to generate report of course change
        public function getReportChangeCourse($periodo) {
            // user token validation
            $this->validateToken();

            // user privilege validation
            $this->validatePrivilege([1, 2, 4]);

            // start transaction
            $this->beginTransaction();
            // ========================>

            // SQL query
            $statementReportChangeCourse = $this->preConsult(
                "SELECT (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                ((CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante 
                ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) || ' ' 
                || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                withdrawal_date, (oldc.grado_curso || oldc.letra_curso) as old_course, old_list_number,
                new_assignment_date, (newc.grado_curso || newc.letra_curso) as new_course, new_list_number
                FROM libromatricula.change_course_log AS log
                INNER JOIN libromatricula.registro_matricula AS m ON m.id_registro_matricula = log.id_registro_matricula
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                INNER JOIN libromatricula.registro_curso AS oldc ON oldc.id_curso = log.id_old_course
                INNER JOIN libromatricula.registro_curso AS newc ON newc.id_curso = log.id_new_course
                WHERE m.anio_lectivo_matricula = ? AND old_list_number IS NOT NULL
                ORDER BY new_assignment_date;"
            );

            try {
                // SQL query execution
                $statementReportChangeCourse->execute([intval($periodo)]);

                // confirm transaction
                $this->commit();
                // ========================>

                // obtaining object with query data
                $reportChangeCourse = $statementReportChangeCourse->fetchAll(PDO::FETCH_OBJ);

                // create a excel object
                $file = $this->createExcelObject("Registro cambios curso");

                // selection and modification of the main sheet
                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro cambios de curso");
                $sheetActive->setShowGridLines(false); 
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // excel sheet title
                $sheetActive->setCellValue('A1', 'Cambios de curso periodo '. $periodo);

                // applying filter on headers
                $sheetActive->setAutoFilter('A3:H3'); 

                // application of styles on headers
                $sheetActive->getStyle('A3:H3')->applyFromArray($this->styleTitle);

                // view lock for headers
                $sheetActive->freezePane('A4');

                // cell width
                $sheetActive->getColumnDimension('A')->setWidth(20);
                $sheetActive->getColumnDimension('B')->setWidth(45);
                $sheetActive->getColumnDimension('C')->setWidth(15);
                $sheetActive->getColumnDimension('D')->setWidth(20);
                $sheetActive->getColumnDimension('E')->setWidth(20);
                $sheetActive->getColumnDimension('F')->setWidth(15);
                $sheetActive->getColumnDimension('G')->setWidth(20);
                $sheetActive->getColumnDimension('H')->setWidth(20);

                // cell content alignment
                $sheetActive->getStyle('C:H')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('C3:H3')->getAlignment()->setHorizontal('left');

                // header titles
                $sheetActive->setCellValue('A3', 'RUT ESTUDIANTE');
                $sheetActive->setCellValue('B3', 'NOMBRES ESTUDIANTE');
                $sheetActive->setCellValue('C3', 'FECHA BAJA');
                $sheetActive->setCellValue('D3', 'CURSO ANTIGUO');
                $sheetActive->setCellValue('E3', 'N LISTA ANTIGUO');
                $sheetActive->setCellValue('F3', 'FECHA ALTA');
                $sheetActive->setCellValue('G3', 'CURSO NUEVO');
                $sheetActive->setCellValue('H3', 'N LISTA NUEVO');

                // main writing row
                $fila = 4;

                // traversal of data object to insert into rows (recorrido del objeto de datos para insertar en filas)
                foreach ($reportChangeCourse as $change) {
                    $sheetActive->setCellValue('A'.$fila, $change->rut_estudiante);
                    $sheetActive->setCellValue('B'.$fila, $change->nombres_estudiante);
                    $sheetActive->setCellValue('C'.$fila, $change->withdrawal_date);
                    $sheetActive->setCellValue('D'.$fila, $change->old_course);
                    $sheetActive->setCellValue('E'.$fila, $change->old_list_number);
                    $sheetActive->setCellValue('F'.$fila, $change->new_assignment_date);
                    $sheetActive->setCellValue('G'.$fila, $change->new_course);
                    $sheetActive->setCellValue('H'.$fila, $change->new_list_number);

                    $fila++;
                }

                // excel file download ========================>
                $this->downloadExcelFile($file, "ReporteCambioCursos_", $periodo);

            } catch (Exception $error) {
                // roll back transaction on error
                $this->rollBack();
                // ========================>

                // getting postgreSQL error, if exists
                $messageError = ErrorHandler::handleError($error, $statementReportChangeCourse);

                // custom exception for errors
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // closing the connection with the database
                $this->closeConnection();
            };
        }









        // =============> FUNCIONALIDADES PARA TRABAJAR POSTERIOR AL PROCESO DE MATRICULA
        public function getReportAltas() {}


        public function getReportBajas() {}


        public function getReportCambioApoderados() {}


        public function getReportCambioCurso() {}
        
    }



?>