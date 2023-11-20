<?php

    $student = new Models\Student;

    Flight::route('GET /student/getAll', [$student, "getStudentAll"]);
    Flight::route('GET /student/getStudent/@rut_student', [$student, "getStudent"]);
    Flight::route('GET /student/getNameStudent/@rut_estudiante/@periodo', [$student, "getNameStudent"]);
    Flight::route('POST /student/setStudent', [$student, "setStudent"]);
    Flight::route('PUT /student/updateStudent', [$student, "updateStudent"]);



    
    Flight::route('DELETE /student/delete/@id_estudiante', [$student, "deleteStudent"]);

?>