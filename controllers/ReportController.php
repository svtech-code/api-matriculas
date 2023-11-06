<?php
    $report = new Models\Report;

    Flight::route('GET /report/getCertificadoMatricula/@rut/@periodo', [$report, "getCertificadoMatricula"]);
    Flight::route('GET /report/getCertificadoAlumnoRegular/@rut/@periodo', [$report, "getCertificadoAlumnoRegular"]);


?>