<?php

    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;

    class Curso extends Auth {
        public function __construct() {
            parent::__construct();
        }

        public function getCountGrade($periodo) {
            $this->validateToken();
        }

        
            
        
    }



?>