<?php
    namespace Models;

    use Models\Auth;
    use Exception;
    use Flight;
    use PDO;

    class Report extends Auth {
        public function __construct() {
            parent::__construct();
        }

        // metodo para obtener certificado de matricula
        public function getCertificadoMatricula($matricula) {

        }
        
    }



?>