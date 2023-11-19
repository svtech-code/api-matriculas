<?php

namespace Models;

use Models\Auth;
use Exception;
use Flight;
use PDO;

class Student extends Auth {
    public function __construct() {
        parent::__construct();
    }

    // metodo para obtener los datos del estudiantes
    // method to obtain student data
    public function getStudent($rut_student) {
        $this->validateToken();
        $statmentStudent = $this->preConsult(
            "SELECT e.id_estudiante, e.nombres_estudiante, e.nombre_social_estudiante,
            e.apellido_paterno_estudiante, e.apellido_materno_estudiante, 
            e.fecha_nacimiento_estudiante, e.sexo_estudiante
            FROM libromatricula.registro_estudiante AS e
            WHERE e.rut_estudiante = ?;"
        );

        try {
            $statmentStudent->execute([$rut_student]);
            $student = $statmentStudent->fetch(PDO::FETCH_OBJ);
            if (!$student) {
                Flight::halt(200, json_encode([
                    "message" => "estudiante no registrado"
                ]));
            }

            $this->array = [
                "id" => $student->id_estudiante,
                "nombres" => $student->nombres_estudiante,
                "nombre_social" => $student->nombre_social_estudiante,
                "paterno" => $student->apellido_paterno_estudiante,
                "materno" => $student->apellido_materno_estudiante,
                "fecha_nacimiento" => $student->fecha_nacimiento_estudiante,
                "sexo" => $student->sexo_estudiante,
            ];
            Flight::json($this->array);

        } catch(Exception $error) {
            Flight::halt(400, json_encode([
                "message" => "Error: ". $error->getMessage()
            ]));
            
        } finally {
            $this->closeConnection();
        }
    }

    // metodo para obtener el nombre del estudiante
    // method to obtain the student`s name
    public function getNameStudent($rut_student, $periodo) {
        $this->validateToken();
        $responseSearch = false;

        // devuelve un booleano
        $statementSearchStudent = $this->preConsult(
            "SELECT EXISTS (SELECT lista.rut_estudiante
            FROM libromatricula.lista_sae AS lista
            WHERE lista.rut_estudiante = ?
            AND lista.periodo_matricula = ?);"
        );


        $statmentStudent = $this->preConsult(
            "SELECT e.id_estudiante,
            (CASE WHEN e.nombre_social_estudiante IS NULL
            THEN e.nombres_estudiante
            ELSE '(' || e.nombre_social_estudiante || ') ' || nombres_estudiante END)
            || ' ' || e.apellido_paterno_estudiante || ' ' || apellido_materno_estudiante AS estudiante
            FROM libromatricula.registro_estudiante AS e
            WHERE e.rut_estudiante = ?"
        );

        try {

            if ($periodo !== date('Y')) {
                $statementSearchStudent->execute([$rut_student, $periodo]);
                $responseSearch = $statementSearchStudent->fetchColumn();
    
                if (!$responseSearch) {
                    Flight::halt(400, json_encode([
                        "message" => "El rut no esta en lista SAE !"
                    ]));
                } 
            }

            $statmentStudent->execute([$rut_student]);
            $student = $statmentStudent->fetch(PDO::FETCH_OBJ);
            if (!$student) {
                Flight::halt(200, json_encode([
                    "message" => "Sin registro de estudiante !"
                ]));
            }

            $this->array = [
                "id" => $student->id_estudiante,
                "nombres" => $student->estudiante,
            ];
            Flight::json($this->array);

        } catch(Exception $error) {
            Flight::halt(400, json_encode([
                "message" => "Error: ". $error->getMessage()
            ]));
            
        } finally {
            $this->closeConnection();
        }
    }

    // metodo para actualizar los datos de un estudiante
    public function updateStudent() {
        $this->validateToken();

        $student = Flight::request()->data;
        $statementUpdateStudent = $this->preConsult(
            "UPDATE libromatricula.registro_estudiante
            SET rut_estudiante = ?, dv_rut_estudiante = ?, 
            apellido_paterno_estudiante = ?, apellido_materno_estudiante = ?,
            nombres_estudiante = ?, nombre_social_estudiante = ?,
            fecha_nacimiento_estudiante = ?, sexo_estudiante = ?,
            fecha_modificacion_estudiante = CURRENT_TIMESTAMP
            WHERE id_estudiante = ?;"
        );

        try {
            $statementUpdateStudent->execute([
                $student->rut,
                strtoupper($student->dv_rut),
                strtoupper($student->paterno),
                strtoupper($student->materno),
                strtoupper($student->nombres),
                $student->nombre_social ? strtoupper($student->nombre_social) : null,
                $student->fecha_nacimiento,
                $student->sexo,
                intval($student->id)
            ]);

        } catch (Exception $error) {
            Flight::halt(400, json_encode([
                "message" => "Error: ". $error->getMessage()
            ]));
        } finally {
            $this->closeConnection();
        }
    }

    




    // ================ cambiar a nuevo registro de estudiantes ====>

    public function setStudent() {
        // agregar validacion para evitar que un usuario sin privilegios pueda ingresar datos
        $this->validateToken();
        $student = Flight::request()->data;
        $statementSetStudent = $this->preConsult(
            "INSERT INTO estudiante 
            (rut_estudiante, dv_rut_estudiante, ap_estudiante, am_estudiante, nombres_estudiante,
            nombre_social, fecha_nacimiento, sexo, fecha_ingreso)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);"
        );

        try {
            $statementSetStudent->execute(
                [$student->rut, $student->dv_rut, $student->paterno, $student->materno, $student->nombres, 
                ($student->n_social == '') ? null : $student->n_social, 
                $student->f_nacimiento, $student->sexo, $student->f_ingreso]
            );

            $this->array = ["message" => "success"];
            Flight::json($this->array);

        } catch (Exception $error) {
            Flight::halt(400, json_encode([
                "message" => "Error: ". $error->getMessage()
            ]));
        } finally {
            $this->closeConnection();
        }
    }

    public function getStudentAll() {
        $this->validateToken();
        $statmentStudent = $this->preConsult(
            "SELECT (rut_estudiante || '-' || dv_rut_estudiante) AS rut_estudiante,
                id_estudiante, nombres_estudiante, ap_estudiante, am_estudiante
            FROM estudiante;"
        );

        try {
            $statmentStudent->execute();
            $students =  $statmentStudent->fetchAll(PDO::FETCH_ASSOC);
            foreach($students as $student) {
                $this->array[] = [
                    "id_estudiante" => $student['id_estudiante'],
                    "rut_estudiante" => $student['rut_estudiante'],
                    "nombres_estudiante"  =>  $student['nombres_estudiante'],
                    "ap_estudiante" => $student['ap_estudiante'],
                    "am_estudiante" => $student['am_estudiante'],
                ];
            }
            Flight::json($this->array);

        } catch (Exception $error) {
            Flight::halt(404, json_encode([
                "message" => "Error: ". $error->getMessage(),
            ]));

        } finally {
            $this->closeConnection();
        }
    }

    public function deleteStudent($id_estudiante) {
        $this->validateToken();
        $statementDeleteStudent = $this->preConsult(
            "DELETE FROM estudiante WHERE id_estudiante = ?;"
        );

        try {
            $statementDeleteStudent->execute([intval($id_estudiante)]);
            $this->array = ["message" => "success"];
            Flight::json($this->array);

        } catch (Exception $error) {
            Flight::halt(400, json_encode([
                "message" => "Error: ". $error->getMessage()
            ]));
        } finally {
            $this->closeConnection();
        }

    }

    // Agregar validación de privilegio para editar y eliminar datos
    // cambiar consultas a tabla registro matricula
    // trabajar con variables de proceso matricula, para validar registros en tabla registro_SAE


}

?>