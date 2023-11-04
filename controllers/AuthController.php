<?php

    $auth = new Models\Auth;

    Flight::route('POST /auth', [$auth, "auth"]);
    Flight::route('GET /validateSession', [$auth, "validateSession"]);

?>