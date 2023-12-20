<?php
    $matricula = new Models\Matricula;

    // peticiones GET
    Flight::route('GET /matricula/getPeriodoMatricula', [$matricula, "getPeriodoMatricula"]);
    Flight::route('GET /matricula/getCount/@periodo', [$matricula, "getCountAltasBajas"]);
    Flight::route('GET /matricula/getAll/@periodo', [$matricula, "getMatriculaAll"]);
    Flight::route('GET /matricula/getMatricula/@id', [$matricula, "getMatricula"]);
    Flight::route('GET /matricula/getStatusProcessMatricula/@periodo', [$matricula, "StatusProcessMatricula"]);
    
    // peticiones POST
    Flight::route('POST /matricula/setMatricula', [$matricula, "setMatricula"]);

    // peticiones PUT
    Flight::route('PUT /matricula/updateMatricula', [$matricula, "updateMatricula"]);




    
    
    // Flight::route('DELETE /matricula/deleteMatricula/@id', [$matricula, "deleteMatricula"]);
    // Flight::route('GET /matricula/getNumber/@grade/@periodo', [$matricula, "getNumberMatricula"]);

    // PROBAR NUEVA FORMA DE INSERTAR REGISTRO Y AGREGAR EN EL INSER SELECT DE NUMERO DE MATRICULA
    // Flight::route('POST /matricula/pruebaSetMatricula', [$matricula, "pruebaSetMatricula"]);


    
?>