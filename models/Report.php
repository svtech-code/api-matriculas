<?php
    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;
    use PhpOffice\PhpWord\TemplateProcessor;
    use PhpOffice\PhpWord\Settings;
    use PhpOffice\PhpSpreadsheet\{Spreadsheet, IOFactory};
    use PhpOffice\PhpSpreadsheet\Style\{Color, Font};

    class Report extends Auth {
        private $tempDir = './document';
        private $currentMonth = '';
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
            if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);
            Settings::setTempDir($this->tempDir);
            $this->currentMonth = date('F');
        }

        // metodo para obtener certificado de matricula
        public function getCertificadoMatricula($rut, $periodo) {
            $this->validateToken();

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
                // ejecucion de la consulta SQL
                $statementReport->execute([$rut, intval($periodo)]);
                $report = $statementReport->fetch(PDO::FETCH_OBJ);

                // ruta de las plantillas de word
                $templateCertificadoMatricula = './document/certificadoMatricula.docx';
                $templateCertificadoMatriculaTemp = './document/certificadoMatricula_temp.docx';
    
                // crear un objeto TemplateProcessor
                $file = new TemplateProcessor($templateCertificadoMatricula);
    
                // asignación de los datos dinamicos
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
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));
            
            } finally {
                $this->closeConnection();
            }
        }

        // metodo para obtener certificado de alumno regular
        public function getCertificadoAlumnoRegular($rut, $periodo) {
            $this->validateToken();

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
                // ejecucion de la consulta SQL
                $statementReport->execute([$rut, intval($periodo)]);
                $report = $statementReport->fetch(PDO::FETCH_OBJ);
                
                if (!$report) {
                    Flight::halt(400, json_encode([
                        "message" => "Matricula sin curso asignado",
                    ]));
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
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }

        }

        public function getReportMatricula($dateFrom, $dateTo, $periodo) {
            $this->validateToken();

            // consulta SQL
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
                // ejecucion de la consulta SQL
                $statementReportMatricula->execute([intval($periodo), $dateFrom, $dateTo]);
                $reportMatricula = $statementReportMatricula->fetchAll(PDO::FETCH_OBJ);

                $file = new Spreadsheet();
                $file
                    ->getProperties()
                    ->setCreator("Dpto. Informática")
                    ->setLastModifiedBy('Informática')
                    ->setTitle('Registro matrícula');

                $file->setActiveSheetIndex(0);
                $sheetActive = $file->getActiveSheet();
                $sheetActive->setTitle("Registro de matrículas");
                $sheetActive->setShowGridLines(false);
                $sheetActive->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                // $sheetActive->getStyle('A3:Y3')->getFont()->setBold(true)->setSize(12);
                // $sheetActive->setAutoFilter('A3:Y3');

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
                // $sheetActive->getStyle('A:C')->getAlignment()->setHorizontal('center');
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

                // agregar el estado de la matricula !!


                $fila = 4;
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

                $writer = IOFactory::createWriter($file, 'Xlsx');
                $writer->save('php://output');

            } catch (Exception $error) {
                Flight::halt(400, json_encode([
                    "message" => "Error: ". $error->getMessage()
                ]));

            } finally {
                $this->closeConnection();
            }

        }









        // =============> FUNCIONALIDADES PARA TRABAJAR POSTERIOR AL PROCESO DE MATRICULA
        public function getReportAltas() {}


        public function getReportBajas() {}


        public function getReportCambioApoderados() {}


        public function getReportCambioCurso() {}
        
    }



?>