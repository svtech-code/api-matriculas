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

        // revisar
        private $borderCompleteStyle = [
            'borders' => [
                'allborders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
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

        // método para la creación del archivo excel
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

        // método de descarga del archivo excel
        private function downloadExcelFile($file, $title, $period) {
            // cabeceras de la descarga
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'. $title. '_'. $period. 'xlsx"');
            header('Cache-Control: max-age=0');

            // se genera el archivo excel
            $writer = IOFactory::createWriter($file, 'Xlsx');
            $writer->save('php://output');
        }

        // método para obtener certificado de matricula
        public function getCertificadoMatricula($rut, $periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // consulta SQL
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
                // se ejecuta la consulta
                $statementReport->execute([$rut, intval($periodo)]);

                // se obtiene un objeto con los datos de la consutla
                $report = $statementReport->fetch(PDO::FETCH_OBJ);

                // ruta de las plantillas de word
                $templateCertificadoMatricula = './document/certificadoMatricula.docx';
                $templateCertificadoMatriculaTemp = './document/certificadoMatricula_temp.docx';
    
                // crear un objeto TemplateProcessor
                $file = new TemplateProcessor($templateCertificadoMatricula);
    
                // asignación de los datos dinámicos
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
    
                // guardar el documento word modificado
                $file->saveAs($templateCertificadoMatriculaTemp);
    
                // descargar el documento word generado
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header("Content-Disposition: attachment; filename=$templateCertificadoMatriculaTemp");
                readfile($templateCertificadoMatriculaTemp);
                unlink($templateCertificadoMatriculaTemp);

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

        // método para obtener certificado de alumno regular
        public function getCertificadoAlumnoRegular($rut, $periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2]);

            // consulta SQL
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
                // se ejecuta la consulta
                $statementReport->execute([$rut, intval($periodo)]);

                // se obtiene un objeto con los datos de la consutla
                $report = $statementReport->fetch(PDO::FETCH_OBJ);
                
                // se verifica que los datos se han generado con exito
                if (!$report) {
                    throw new Exception("Matrícula sin curso asignado", 409);
                }

                // ruta de las plantillas de word
                $templateCertificadoAlumnoRegular = './document/certificadoAlumnoRegular.docx';
                $templateCertificadoAlumnoRegularTemp = './document/certificadoAlumnoRegular_temp.docx';
    
                // crear un objeto TemplateProcessor
                $file = new TemplateProcessor($templateCertificadoAlumnoRegular);
    
                // asignación de los datos dinamicos
                // asignación de los datos dinamicos
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
    
                // guardar el documento word modificado
                $file->saveAs($templateCertificadoAlumnoRegularTemp);
    
                // descargar el documento word generado
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header("Content-Disposition: attachment; filename=$templateCertificadoAlumnoRegularTemp");
                readfile($templateCertificadoAlumnoRegularTemp);
                unlink($templateCertificadoAlumnoRegularTemp);

            } catch (Exception $error) {
                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementReport);

                // obtención del codigo de error
                $statusCode = $error->getCode() ? $error->getCode() : 404;

                // expeción personalizada para errores
                Flight::halt($statusCode, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }

        }

        // método para obtener reporte de matrícula
        public function getReportMatricula($dateFrom, $dateTo, $periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 3, 4]);

            // sentencia SQL
            $statementReportMatricula = $this->preConsult(
                "SELECT m.numero_matricula, m.grado, (c.grado_curso || c.letra_curso) AS curso,
                (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                e.apellido_paterno_estudiante, e.apellido_materno_estudiante, e.nombres_estudiante,
                e.nombre_social_estudiante, e.fecha_nacimiento_estudiante, e.sexo_estudiante,
                (apt.rut_apoderado || '-' || apt.dv_rut_apoderado) AS rut_titular,
                apt.apellido_paterno_apoderado AS paterno_titular, apt.apellido_materno_apoderado AS materno_titular,
                apt.nombres_apoderado AS nombres_titular, apt.telefono_apoderado AS telefono_titular, 
                apt.direccion_apoderado AS direccion_titular,
                (aps.rut_apoderado || '-' || aps.dv_rut_apoderado) AS rut_suplente,
                aps.apellido_paterno_apoderado AS paterno_suplente, aps.apellido_materno_apoderado AS materno_suplente,
                aps.nombres_apoderado AS nombres_suplente, aps.telefono_apoderado AS telefono_suplente,
                aps.direccion_apoderado AS direccion_suplente, m.fecha_matricula
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
                // se ejecuta la consulta
                $statementReportMatricula->execute([intval($periodo), $dateFrom, $dateTo]);
                
                // se obtiene un objeto con los datos de la consutla
                $reportMatricula = $statementReportMatricula->fetchAll(PDO::FETCH_OBJ);

                $file = new Spreadsheet();
                $file
                    ->getProperties()
                    ->setCreator('Dpto. Informática')
                    ->setLastModifiedBy('Informática')
                    ->setTitle('Registro matrícula');

                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro de matrículas");
                $sheetActive->setShowGridLines(false);
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                $sheetActive->setAutoFilter('A3:W3');
                $sheetActive->getStyle('A3:W3')->getFont()->setBold(true)->setSize(12);

                // título del excel
                // $sheetActive->mergeCells('A1:D1');
                $sheetActive->setCellValue('A1', 'Registro de matrículas periodo '. $periodo);

                // ancho de las celdas
                $sheetActive->getColumnDimension('A')->setWidth(11);
                $sheetActive->getColumnDimension('B')->setWidth(18);
                $sheetActive->getColumnDimension('C')->setWidth(8);
                $sheetActive->getColumnDimension('D')->setWidth(8);
                $sheetActive->getColumnDimension('E')->setWidth(15);
                $sheetActive->getColumnDimension('F')->setWidth(18);
                $sheetActive->getColumnDimension('G')->setWidth(18);
                $sheetActive->getColumnDimension('H')->setWidth(24);
                $sheetActive->getColumnDimension('I')->setWidth(18);
                $sheetActive->getColumnDimension('J')->setWidth(18);
                $sheetActive->getColumnDimension('K')->setWidth(15);

                $sheetActive->getColumnDimension('L')->setWidth(15);
                $sheetActive->getColumnDimension('M')->setWidth(18);
                $sheetActive->getColumnDimension('N')->setWidth(18);
                $sheetActive->getColumnDimension('O')->setWidth(24);
                $sheetActive->getColumnDimension('P')->setWidth(18);
                $sheetActive->getColumnDimension('Q')->setWidth(40);

                $sheetActive->getColumnDimension('R')->setWidth(15);
                $sheetActive->getColumnDimension('S')->setWidth(18);
                $sheetActive->getColumnDimension('T')->setWidth(18);
                $sheetActive->getColumnDimension('U')->setWidth(24);
                $sheetActive->getColumnDimension('V')->setWidth(18);
                $sheetActive->getColumnDimension('W')->setWidth(40);

                // alineacion del contenido de las celdas
                $sheetActive->getStyle('A:D')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('K')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left');                

                // titulo de la tabla
                $sheetActive->setCellValue('A3', 'MATRÍCULA');
                $sheetActive->setCellValue('B3', 'FECHA_MATRICULA');
                $sheetActive->setCellValue('C3', 'GRADO');
                $sheetActive->setCellValue('D3', 'CURSO');
                $sheetActive->setCellValue('E3', 'RUT_ESTUDIANTE');
                $sheetActive->setCellValue('F3', 'APELLIDO_PATERNO');
                $sheetActive->setCellValue('G3', 'APELLIDO_MATERNO');
                $sheetActive->setCellValue('H3', 'NOMBRES_ESTUDIANTE');
                $sheetActive->setCellValue('I3', 'NOMBRE_SOCIAL');
                $sheetActive->setCellValue('J3', 'FECHA_NACIMIENTO');
                $sheetActive->setCellValue('K3', 'SEXO_ESTUDIANTE');

                // datos apoderado titular
                $sheetActive->setCellValue('L3', 'RUT_TITULAR');
                $sheetActive->setCellValue('M3', 'APELLIDO_PATERNO');
                $sheetActive->setCellValue('N3', 'APELLIDO_MATERNO');
                $sheetActive->setCellValue('O3', 'NOMBRES_TITULAR');
                $sheetActive->setCellValue('P3', 'TELEFONO_TITULAR');
                $sheetActive->setCellValue('Q3', 'DIRECCOIN_TITULAR');

                // datos apoderado suplente
                $sheetActive->setCellValue('R3', 'RUT_SUPLENTE');
                $sheetActive->setCellValue('S3', 'APELLIDO_PATERNO');
                $sheetActive->setCellValue('T3', 'APELLIDO_MATERNO');
                $sheetActive->setCellValue('U3', 'NOMBRES_SUPLENTE');
                $sheetActive->setCellValue('V3', 'TELEFONO_SUPLENTE');
                $sheetActive->setCellValue('W3', 'DIRECCOIN_SUPLENTE');


                $fila = 4;
                // se recorre el objeto para obtener un array con todos los datos de la consulta
                foreach ($reportMatricula as $report) {
                    $sheetActive->setCellValue('A'.$fila, $report->numero_matricula);
                    $sheetActive->setCellValue('B'.$fila, $report->fecha_matricula);
                    $sheetActive->setCellValue('C'.$fila, $report->grado);
                    $sheetActive->setCellValue('D'.$fila, $report->curso);
                    $sheetActive->setCellValue('E'.$fila, $report->rut_estudiante);
                    $sheetActive->setCellValue('F'.$fila, $report->apellido_paterno_estudiante);
                    $sheetActive->setCellValue('G'.$fila, $report->apellido_materno_estudiante);
                    $sheetActive->setCellValue('H'.$fila, $report->nombres_estudiante);
                    $sheetActive->setCellValue('I'.$fila, $report->nombre_social_estudiante);
                    $sheetActive->setCellValue('J'.$fila, $report->fecha_nacimiento_estudiante);
                    $sheetActive->setCellValue('K'.$fila, $report->sexo_estudiante);

                    $sheetActive->setCellValue('L'.$fila, $report->rut_titular);
                    $sheetActive->setCellValue('M'.$fila, $report->paterno_titular);
                    $sheetActive->setCellValue('N'.$fila, $report->materno_titular);
                    $sheetActive->setCellValue('O'.$fila, $report->nombres_titular);
                    $sheetActive->setCellValue('P'.$fila, $report->telefono_titular ? '+569-'. $report->telefono_titular : $report->telefono_titular);
                    $sheetActive->setCellValue('Q'.$fila, $report->direccion_titular);

                    $sheetActive->setCellValue('R'.$fila, $report->rut_suplente);
                    $sheetActive->setCellValue('S'.$fila, $report->paterno_suplente);
                    $sheetActive->setCellValue('T'.$fila, $report->materno_suplente);
                    $sheetActive->setCellValue('U'.$fila, $report->nombres_suplente);
                    $sheetActive->setCellValue('V'.$fila, $report->telefono_suplente ? '+569-'. $report->telefono_suplente : $report->telefono_suplente);
                    $sheetActive->setCellValue('W'.$fila, $report->direccion_suplente);

                    $fila++;
                }

                // cabeceras de la descarga
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="ReporteMatricula_'.$periodo.'xlsx"');
                header('Cache-Control: max-age=0');

                // se genera el archivo excel
                $writer = IOFactory::createWriter($file, 'Xlsx');
                $writer->save('php://output');

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

        // método para obtener reporte de estudiantes y cursos
        public function getReportCourses($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 3, 4]);

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementReportCourses = $this->preConsult(
                "SELECT m.numero_matricula, (c.grado_curso || '' || c.letra_curso) AS curso,
                m.numero_lista_curso AS n_lista,
                to_char(m.fecha_alta_matricula, 'DD/MM/YYYY') AS fecha_alta_matricula,
                to_char(m.fecha_baja_matricula, 'DD/MM/YYYY') AS fecha_baja_matricula,
                e.sexo_estudiante, e.apellido_paterno_estudiante, e.apellido_materno_estudiante,
                (CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante ELSE
                '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) AS nombres_estudiante,
                (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante, ee.estado AS estado_estudiante
                FROM libromatricula.registro_matricula AS m
                LEFT JOIN libromatricula.registro_curso AS c ON c.id_curso = m.id_curso
                LEFT JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                INNER JOIN libromatricula.registro_estado AS ee ON ee.id_estado = m.id_estado_matricula
                WHERE m.anio_lectivo_matricula = ?
                order by curso, m.numero_lista_curso"
            );

            try {
                // se ejecuta la consulta
                $statementReportCourses->execute([intval($periodo)]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // se obtiene un objeto con los datos de la consulta
                $reportCourses = $statementReportCourses->fetchAll(PDO::FETCH_OBJ);

                // creación del objeto excel
                $file = $this->createExcelObject("Registro cursos");

                // se comienza a trabajar con la seleccion de hojas y celdas
                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro de cursos");
                $sheetActive->setShowGridLines(false);                
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // titulo de la hoja de excel
                $sheetActive->setCellValue('A1', 'Registro de cursos periodo '. $periodo);

                // filtro para los encabezados
                $sheetActive->setAutoFilter('A3:K3');   
                $sheetActive->getStyle('A3:K3')->applyFromArray($this->styleTitle);


                // ancho de las celdas
                $sheetActive->getColumnDimension('A')->setWidth(18);
                $sheetActive->getColumnDimension('B')->setWidth(14);
                $sheetActive->getColumnDimension('C')->setWidth(14);
                $sheetActive->getColumnDimension('D')->setWidth(18);
                $sheetActive->getColumnDimension('E')->setWidth(18);
                $sheetActive->getColumnDimension('F')->setWidth(14);
                $sheetActive->getColumnDimension('G')->setWidth(22);
                $sheetActive->getColumnDimension('H')->setWidth(22);
                $sheetActive->getColumnDimension('I')->setWidth(30);
                $sheetActive->getColumnDimension('J')->setWidth(22);
                $sheetActive->getColumnDimension('K')->setWidth(22);

                // alineación del contenido de las celdas
                $sheetActive->getStyle('A:F')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('I')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left'); 

                // título de las columnas
                $sheetActive->setCellValue('A3', 'Nº MATRICULA');
                $sheetActive->setCellValue('B3', 'CURSO');
                $sheetActive->setCellValue('C3', 'N LISTA');
                $sheetActive->setCellValue('D3', 'FECHA ALTA');
                $sheetActive->setCellValue('E3', 'FECHA RETIRO');
                $sheetActive->setCellValue('F3', 'SEXO');
                $sheetActive->setCellValue('G3', 'APELLIDO PATERNO');
                $sheetActive->setCellValue('H3', 'APELLIDO MATERNO');
                $sheetActive->setCellValue('I3', 'NOMBRES');
                $sheetActive->setCellValue('J3', 'RUT ESTUDIANTE');
                $sheetActive->setCellValue('K3', 'ESTADO ESTUDIANTE');

                // inicio de la fila
                $fila = 4;

                // se recorre el objeto para obtener un array con todos los datos de la consulta realizada
                foreach ($reportCourses as $course) {
                    $sheetActive->setCellValue('A'.$fila, $course->numero_matricula ? $course->numero_matricula: "N/A");
                    $sheetActive->setCellValue('B'.$fila, $course->curso);
                    $sheetActive->setCellValue('C'.$fila, $course->n_lista ? $course->n_lista : "N/A");
                    $sheetActive->setCellValue('D'.$fila, $course->fecha_alta_matricula);
                    $sheetActive->setCellValue('E'.$fila, $course->fecha_baja_matricula);
                    $sheetActive->setCellValue('F'.$fila, $course->sexo_estudiante);
                    $sheetActive->setCellValue('G'.$fila, $course->apellido_paterno_estudiante);
                    $sheetActive->setCellValue('H'.$fila, $course->apellido_materno_estudiante);
                    $sheetActive->setCellValue('I'.$fila, $course->nombres_estudiante);
                    $sheetActive->setCellValue('J'.$fila, $course->rut_estudiante);
                    $sheetActive->setCellValue('K'.$fila, $course->estado_estudiante);

                    // aplicar estilo color rojo para retirados
                    if ($course->estado_estudiante === 'Retirado (a)') {
                        $sheetActive->getStyle('A'.$fila.':K'.$fila)->applyFromArray($this->styleRetired);
                    }

                    // aplicar estilo color naranjo para suspendidos
                    if ($course->estado_estudiante === 'Suspendido (a)') {
                        $sheetActive->getStyle('A'.$fila.':K'.$fila)->applyFromArray($this->styleOrange);
                    }
                    
                    $fila++;
                }

                // descarga del archivo excel ========================>
                $this->downloadExcelFile($file, "ReporteCursos_", $periodo);

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementReportCourses);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }



        }

        // trabajar en método para descargar reporte por curso !!!!!!
        public function getReportCourse($periodo, $course) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 4]);

            // obtención de letra y grado por separados
            $grado = substr($course, 0, 1);
            $letra = substr($course, 1, 1);
            
            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
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
                // se ejecuta la consulta
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

                // confirmar transacción
                $this->commit();
                // ========================>

                // se obtiene un objeto con los datos de la consulta
                $reportCourseLetter = $statementReportCourseLetter->fetchAll(PDO::FETCH_OBJ);

                // cargar plantilla
                $file = IOFactory::load("./document/nomina_curso.xlsx");

                // obtener la hoja de calculo activa
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Nómina ". $course);

                // título de la nómina
                $sheetActive->setCellValue('C3', "NÓMINA PARA REGISTRO DE ASISTENCIA DIARIA ". $periodo);

                // asignación del curso
                $sheetActive->setCellValue('I6', $course);

                // encargados del curso
                $docenteJefe = "";
                $inspectorGeneral = "";
                $paradocente = "";

                // contador para cantidad de estudiantes por sexo
                $countMale = 0;
                $countFemale = 0;
                $countTotal = 0;

                // inicio de la fila
                $fila = 12;

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

                    // aplicar estilo color rojo para retirados
                    if ($courseLetter->fecha_baja_matricula) {
                        $sheetActive->getStyle('A'.$fila.':I'.$fila)->applyFromArray($this->styleWithdrawal);
                    }

                    // obtención responsables del curso
                    if (empty($docenteJefe)) $docenteJefe = $courseLetter->docente_jefe;
                    if (empty($inspectorGeneral)) $inspectorGeneral = $courseLetter->inspector_general;
                    if (empty($paradocente)) $paradocente = $courseLetter->paradocente;

                    // contador de cantidad de estudiantes por sexo y total
                    if ($courseLetter->sexo_estudiante === 1 && !$courseLetter->fecha_baja_matricula) $countMale++;
                    if ($courseLetter->sexo_estudiante === 2 && !$courseLetter->fecha_baja_matricula) $countFemale++;
                    if (!$courseLetter->fecha_baja_matricula) $countTotal++;
                    
                    $fila++;
                }

                // asignación responsables del curso
                $sheetActive->setCellValue('F6', $docenteJefe);
                $sheetActive->setCellValue('F7', $inspectorGeneral);
                $sheetActive->setCellValue('F8', $paradocente);

                // asignar cantidades al Excel
                $sheetActive->setCellValue('F58', $countMale);
                $sheetActive->setCellValue('F59', $countFemale);
                $sheetActive->setCellValue('F60', $countTotal);

                // Establecer la zona horaria de Chile
                $zonaHorariaChile = new DateTimeZone('America/Santiago');
                
                // Crear un objeto DateTime con la zona horaria de Chile
                $horaActualChile = new DateTime('now', $zonaHorariaChile);
                
                // insertar fecha y hora actual en que se genera la nómina
                $sheetActive->setCellValue('I61', $horaActualChile->format('d-m-Y H:i:s'));

                // Configuración del encabezado HTTP para la descarga del archivo
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="Nómina_2024.xlsx"');
                header('Cache-Control: max-age=0');

                // Generar el archivo
                $writer = new Xlsx($file);

                // Descarga del archivo Excel
                $writer->save('php://output');

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementReportCourseLetter);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
                $this->closeConnection();
            }





        }

        // método para obtener reporte del proceso de matrícula
        public function getReportProcessMatricula($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 4]);

            // sentencia SQL
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
                // se ejecuta la consulta
                $statementReportProcessMatricula->execute([intval($periodo), intval($periodo)]);
                
                // se obtiene un objeto con los datos de la consutla
                $reportProcessMatricula = $statementReportProcessMatricula->fetchAll(PDO::FETCH_OBJ);

                // creación del objeto excel
                $file = new Spreadsheet();
                $file
                    ->getProperties()
                    ->setCreator('Dpto. Informática')
                    ->setLastModifiedBy('Informática')
                    ->setTitle('Registro proceso matrícula');

                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle('Registro proceso matrícula');
                $sheetActive->setShowGridlines(false);
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                $sheetActive->setAutoFilter('A3:G3');
                $sheetActive->getStyle('A3:G3')->getFont()->setBold(true)->setSize(12);

                // título del excel
                $sheetActive->setCellValue('A1', 'Registro Proceso matrícula periodo '. $periodo);

                // ancho de las celdas
                $sheetActive->getColumnDimension('A')->setWidth(15);
                $sheetActive->getColumnDimension('B')->setWidth(20);
                $sheetActive->getColumnDimension('C')->setWidth(10);
                $sheetActive->getColumnDimension('D')->setWidth(40);
                $sheetActive->getColumnDimension('E')->setWidth(20);
                $sheetActive->getColumnDimension('F')->setWidth(20);
                $sheetActive->getColumnDimension('G')->setWidth(20);

                // alineación del contenido de las celdas
                $sheetActive->getStyle('A:C')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('E:G')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left');

                // titulo de la tabla
                $sheetActive->setCellValue('A3', 'MATRICULA');
                $sheetActive->setCellValue('B3', 'RUT');
                $sheetActive->setCellValue('C3', 'GRADO');
                $sheetActive->setCellValue('D3', 'NOMBRES ESTUDIANTE');
                $sheetActive->setCellValue('E3', 'TIPO');
                $sheetActive->setCellValue('F3', 'ESTADO');
                $sheetActive->setCellValue('G3', 'FECHA MATRICULA');

                // datos de la tabla
                $fila = 4;
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

                // cabeceras de la descarga
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="ReporteMatricula_'.$periodo.'xlsx"');
                header('Cache-Control: max-age=0');

                // se crea el archivo excel
                $writer = IOFactory::createWriter($file, 'Xlsx');
                $writer->save('php://output');

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

        // método para obtener reporte del cambio de cursos
        public function getReportChangeCourse($periodo) {
            // se valida el token del usuario
            $this->validateToken();

            // se validan los privilegios del usuario
            $this->validatePrivilege([1, 2, 4]);

            // iniciar transaccion
            $this->beginTransaction();
            // ========================>

            // sentencia SQL
            $statementReportChangeCourse = $this->preConsult(
                " SELECT (e.rut_estudiante || '-' || e.dv_rut_estudiante) AS rut_estudiante,
                ((CASE WHEN e.nombre_social_estudiante IS NULL THEN e.nombres_estudiante 
                ELSE '(' || e.nombre_social_estudiante || ') ' || e.nombres_estudiante END) || ' ' 
                || e.apellido_paterno_estudiante || ' ' || e.apellido_materno_estudiante) AS nombres_estudiante,
                (oldc.grado_curso || oldc.letra_curso) as old_course, 
                old_list_number, (newc.grado_curso || newc.letra_curso) as new_course, new_list_number, new_assignment_date
                FROM libromatricula.change_course_log AS log
                INNER JOIN libromatricula.registro_matricula AS m ON m.id_registro_matricula = log.id_registro_matricula
                INNER JOIN libromatricula.registro_estudiante AS e ON e.id_estudiante = m.id_estudiante
                INNER JOIN libromatricula.registro_curso AS oldc ON oldc.id_curso = log.id_old_course
                INNER JOIN libromatricula.registro_curso AS newc ON newc.id_curso = log.id_new_course
                WHERE m.anio_lectivo_matricula = ?
                ORDER BY new_assignment_date;"
            );

            try {
                // se ejecuta la consulta
                $statementReportChangeCourse->execute([intval($periodo)]);

                // confirmar transacción
                $this->commit();
                // ========================>

                // se obtiene un objeto con los datos de la consulta
                $reportChangeCourse = $statementReportChangeCourse->fetchAll(PDO::FETCH_OBJ);

                // Flight::json($reportChangeCourse);
                // creación del objeto excel
                $file = $this->createExcelObject("Registro cambios curso");

                // se comienza a trabajar con la seleccion de hojas y celdas
                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro cambios de curso");
                $sheetActive->setShowGridLines(false); 
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                
                // titulo de la hoja de excel
                $sheetActive->setCellValue('A1', 'Cambios de curso periodo '. $periodo);


                // filtro para los encabezados
                $sheetActive->setAutoFilter('A3:G3'); 
                $sheetActive->getStyle('A3:G3')->applyFromArray($this->styleTitle);

                // ancho de las celdas
                $sheetActive->getColumnDimension('A')->setWidth(20);
                $sheetActive->getColumnDimension('B')->setWidth(45);
                $sheetActive->getColumnDimension('C')->setWidth(20);
                $sheetActive->getColumnDimension('D')->setWidth(20);
                $sheetActive->getColumnDimension('E')->setWidth(20);
                $sheetActive->getColumnDimension('F')->setWidth(20);
                $sheetActive->getColumnDimension('G')->setWidth(24);

                // alineación del contenido de las celdas
                $sheetActive->getStyle('C:F')->getAlignment()->setHorizontal('center');
                $sheetActive->getStyle('C3:F3')->getAlignment()->setHorizontal('left');
                // // $sheetActive->getStyle('I')->getAlignment()->setHorizontal('center');
                // // $sheetActive->getStyle('A1')->getAlignment()->setHorizontal('left'); 

                // título de las columnas
                $sheetActive->setCellValue('A3', 'RUT ESTUDIANTE');
                $sheetActive->setCellValue('B3', 'NOMBRES ESTUDIANTE');
                $sheetActive->setCellValue('C3', 'CURSO ANTIGUO');
                $sheetActive->setCellValue('D3', 'N LISTA ANTIGUO');
                $sheetActive->setCellValue('E3', 'CURSO NUEVO');
                $sheetActive->setCellValue('F3', 'N LISTA NUEVO');
                $sheetActive->setCellValue('G3', 'FECHA DEL CAMBIO');

                // inicio de la fila
                $fila = 4;

                // se recorre el objeto para obtener un array con todos los datos de la consulta realizada
                foreach ($reportChangeCourse as $change) {
                    $sheetActive->setCellValue('A'.$fila, $change->rut_estudiante);
                    $sheetActive->setCellValue('B'.$fila, $change->nombres_estudiante);
                    $sheetActive->setCellValue('C'.$fila, $change->old_course);
                    $sheetActive->setCellValue('D'.$fila, $change->old_list_number);
                    $sheetActive->setCellValue('E'.$fila, $change->new_course);
                    $sheetActive->setCellValue('F'.$fila, $change->new_list_number);
                    $sheetActive->setCellValue('G'.$fila, $change->new_assignment_date);
                    
                    $fila++;
                }

                // descarga del archivo excel ========================>
                $this->downloadExcelFile($file, "ReporteCambioCursos_", $periodo);

            } catch (Exception $error) {
                // revertir transaccion en caso de error
                $this->rollBack();
                // ========================>

                // obtencion de mensaje de error de postgreSQL si existe
                $messageError = ErrorHandler::handleError($error, $statementReportChangeCourse);

                // expeción personalizada para errores
                Flight::halt(404, json_encode([
                    "message" => "Error: ". $messageError,
                ]));

            } finally {
                // cierre de la conexión con la base de datos
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