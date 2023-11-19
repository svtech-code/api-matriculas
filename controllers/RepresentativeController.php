<?php

    $representative = new Models\Representative;

    Flight::route('GET /representative/getRepresentative/@rut_representative', [$representative, "getRepresentative"]);
    Flight::route('GET /representative/getNameRepresentative/@rut_representative', [$representative, "getNameRepresentative"]);
    Flight::route('POST /representative/setRepresentative', [$representative, "setRepresentative"]);
    Flight::route('PUT /representative/updateRepresentative', [$representative, "updateRepresentative"]);


    // Flight::route('DELETE /student/delete/@id_estudiante', [$student, "deleteStudent"]);

?>