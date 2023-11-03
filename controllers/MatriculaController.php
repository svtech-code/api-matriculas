<?php
    $matricula = new Models\Matricula;

    Flight::route('GET /matricula/getPeriodoMatricula', [$matricula, "getPeriodoMatricula"]);
    Flight::route('GET /matricula/getCount/@periodo', [$matricula, "getCountAltasBajas"]);
    Flight::route('GET /matricula/getAll/@periodo', [$matricula, "getMatriculaAll"]);
    Flight::route('GET /matricula/getMatricula/@id', [$matricula, "getMatricula"]);
    Flight::route('POST /matricula/setMatricula', [$matricula, "setMatricula"]);
    Flight::route('DELETE /matricula/deleteMatricula/@id', [$matricula, "deleteMatricula"]);

    Flight::route('PUT /matricula/updateMatricula', [$matricula, "updateMatricula"]);
    
    // Flight::route('GET /matricula/getNumber/@grade', [$matricula, "getNumberMatricula"]);
    
?>