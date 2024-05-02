<?php
    $report = new Models\Report;

    Flight::route('GET /report/getCertificadoMatricula/@rut/@periodo', [$report, "getCertificadoMatricula"]);
    Flight::route('GET /report/getCertificadoAlumnoRegular/@rut/@periodo', [$report, "getCertificadoAlumnoRegular"]);
    Flight::route('GET /report/getReportMatricula/@dateFrom/@dateTo/@periodo', [$report, "getReportMatricula"]);
    Flight::route('GET /report/getReportProcessMatricula/@periodo', [$report, "getReportProcessMatricula"]);

    
    Flight::route('GET /report/getReportWithdrawal/@dateFrom/@dateTo/@periodo', [$report, "getReportWithdrawal"]);


    Flight::route('GET /report/getReportCourses/@periodo', [$report, "getReportCourses"]);
    Flight::route('GET /report/getReportCourse/@periodo/@course', [$report, "getReportCourse"]);
    Flight::route('GET /report/getReportChangeCourse/@periodo', [$report, "getReportChangeCourse"]);





?>