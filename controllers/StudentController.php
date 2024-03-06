<?php

    $student = new Models\Student;

    // peticiones GET



    // peticiones POST
    Flight::route('POST /student/setStudent', [$student, "setStudent"]);


    // peticiones PUT
    Flight::route('PUT /student/updateStudent', [$student, "updateStudent"]);


    // peticiones PDELETE
    
    
    
    // REVISAR !!!
    Flight::route('GET /student/getNameStudent/@rut_estudiante/@periodo', [$student, "getNameStudent"]);
    Flight::route('GET /student/getStudent/@rut_student', [$student, "getStudent"]);
    Flight::route('GET /student/getAll', [$student, "getStudentAll"]);
    Flight::route('DELETE /student/delete/@id_estudiante', [$student, "deleteStudent"]);

?>