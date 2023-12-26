<?php

    // condicional para trabajar con las cabeceras /cors
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header("Access-Control-Allow-Origin: *");
        header('Access-Control-Allow-Credentials: true');
        header("Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        exit;
    }

    require 'vendor/autoload.php';

    // lectura de variables de entorno
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // AUTHENTICATION --------------------------------------------------->
    require_once "./controllers/AuthController.php";

    // MATRICULA -------------------------------------------------------->
    require_once "./controllers/MatriculaController.php";

    // COURSE -------------------------------------------------------->
    require_once "./controllers/CourseController.php";

    // STUDENT ---------------------------------------------------------->
    require_once "./controllers/StudentController.php";

    // REPRESENTATIVE ---------------------------------------------------------->
    require_once "./controllers/RepresentativeController.php";

    // REPORT ---------------------------------------------------------->
    require_once "./controllers/ReportController.php";


    // inicialización de la librería
    Flight::start();

?>