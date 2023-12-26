<?php

    $auth = new Models\Auth;
    
    // peticiones POST
    Flight::route('POST /auth', [$auth, "auth"]);
    Flight::route('GET /validateSession', [$auth, "validateSession"]);

    
    
    
    // pruebas
    
    
    // Flight::route('GET /auth/validatePrivilege/@necessaryPrivileges/@currentPrivilege', [$auth, "validatePrivilege"]);
    // Flight::route('GET /auth/pruebaDescarga', [$auth, "pruebaDescarga"]);

?>