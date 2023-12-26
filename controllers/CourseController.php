<?php

    $course = new Models\Course;

    Flight::route('GET /course/getCourseAll/@periodo', [$course, "getCourseAll"]);

    // Flight::route('GET /course/getCountGrade/@periodo', [$course, "getCountGrade"]);


?>