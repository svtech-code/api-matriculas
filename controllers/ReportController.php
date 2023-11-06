<?php
    $report = new Models\Report;

    Flight::route('GET /report/getCertificadoMatricula', [$report, "getCertificadoMatricula"]);
    Flight::route('GET /report/getCertificadoAlumnoRegular', [$report, "getCertificadoAlumnoRegular"]);


?>