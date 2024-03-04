<?php

    $course = new Models\Course;

    // peticiones GET
    Flight::route('GET /course/getCourseAll/@periodo', [$course, "getCourseAll"]);
    Flight::route('GET /course/getListCourse/@periodo', [$course, "getListCourse"]);
    Flight::route('GET /course/getClassStartDate/@periodo', [$course, "getClassStartDate"]);

    // peticiones POST


    // peticiones PUT
    Flight::route('PUT /course/updateLetterCourse', [$course, "updateLetterCourse"]);



?>