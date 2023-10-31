<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Credentials: true');
    header("Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type,  Authorization");
    exit;
}

require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// AUTHENTICATION --------------------------------------------------->
require_once "./controllers/AuthController.php";

// MATRICULA -------------------------------------------------------->
require_once "./controllers/MatriculaController.php";

// // STUDENT ---------------------------------------------------------->
require_once "./controllers/StudentController.php";

// REPRESENTATIVE ---------------------------------------------------------->
require_once "./controllers/RepresentativeController.php";


Flight::start();




?>