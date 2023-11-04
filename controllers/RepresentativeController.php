<?php

    $representative = new Models\Representative;

    Flight::route('GET /representative/getName/@rut_representative', [$representative, "getNameRepresentative"]);
    // Flight::route('POST /student/add', [$student, "setStudent"]);
    // Flight::route('PUT /student/update', [$student, "updateStudent"]);
    // Flight::route('DELETE /student/delete/@id_estudiante', [$student, "deleteStudent"]);

?>