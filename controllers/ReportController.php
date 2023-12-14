<?php
    $report = new Models\Report;

    Flight::route('GET /report/getCertificadoMatricula/@rut/@periodo', [$report, "getCertificadoMatricula"]);
    Flight::route('GET /report/getCertificadoAlumnoRegular/@rut/@periodo', [$report, "getCertificadoAlumnoRegular"]);
    Flight::route('GET /report/getReportMatricula/@dateFrom/@dateTo/@periodo', [$report, "getReportMatricula"]);
    Flight::route('GET /report/getReportProcessMatricula/@periodo', [$report, "getReportProcessMatricula"]);





?>