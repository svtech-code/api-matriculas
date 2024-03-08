<?php

    namespace Models;

    class ErrorHandler {

        public static function handleError($error, $query) {
            // se obtiene el error generado desde la base de datos
            $postgreError = $query->errorInfo();

            // se separa el contenido del mensaje que se quiere trabajar
            $pgErrorMessaje = $postgreError[2];

            // se elimina la palabra error generado en el error
            $messageCleared = str_replace('ERROR: ', '', $pgErrorMessaje);

            // sim no existe error desde la base de datos, se pasa el mensaje de la excepcion
            $errorMessaje = $pgErrorMessaje 
                ? explode("CONTEXT:", $messageCleared)[0] 
                : $error->getMessage();

            // se retorna el mensaje de error
            return $errorMessaje;
        }
    }



?>