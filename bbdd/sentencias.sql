-- -> función para obtener el número de matrícula correlativo
-- -> function to obtain the correlative registration number

-- ---------------------------> FUNCION PARA GENERAR NUMERO DE MATRICULA CORRELATIVO

-- CREATE OR REPLACE FUNCTION libromatricula.getNumeroMatricula(grado_param INT, periodo_param INT)
-- RETURNS INT AS $$

-- DECLARE
--     sequence_name VARCHAR;
--     numero_matricula INT;
-- BEGIN
--     -- Determinar el nombre de la secuencia según el grado y el periodo
--     IF grado_param BETWEEN 1 AND 4 THEN
--         sequence_name := 'libromatricula.secuencia_grados_ç1_4_' || periodo_param;
--     ELSIF grado_param BETWEEN 7 AND 8 THEN
--         sequence_name := 'libromatricula.secuencia_grados_7_8_' || periodo_param;
--     ELSE
--         RAISE EXCEPTION 'Grado no válido';
--     END IF;

--     -- Intentar crear la secuencia
--     BEGIN
--         EXECUTE 'CREATE SEQUENCE ' || sequence_name || ' START WITH 1 INCREMENT BY 1 NO MAXVALUE NO CYCLE;';
--     EXCEPTION
--         WHEN duplicate_table THEN
--             -- Ignorar la excepción si la secuencia ya existe
--             NULL;
--     END;

--     -- Obtener el próximo valor de la secuencia
--     EXECUTE 'SELECT nextval($1)' INTO numero_matricula USING sequence_name;

--     RETURN numero_matricula;
-- END;
-- $$ LANGUAGE plpgsql;

-- --------------------------> SENTENCIA


-- ====================================================================>>


-- sentencia proporcionada por don rodrigo

-- create table libromatricula.prueba (id_prueba serial,num_prueba numeric(10,0))
-- alter table libromatricula.prueba add column nivel numeric(1,0);

-- insert into libromatricula.prueba (nivel) values(2) returning num_prueba

-- --drop function f_numero_matricula()
-- CREATE or replace FUNCTION libromatricula.f_numero_matricula() RETURNS trigger
--     AS $$
-- declare var_numero integer;
-- BEGIN

-- select into var_numero
-- max(libromatricula.prueba.num_prueba)
-- from libromatricula.prueba
-- where
-- nivel = new.nivel;
-- new.num_prueba = var_numero+1;
-- RETURN new;
-- END;
-- $$     LANGUAGE plpgsql;

-- --drop trigger trg_numero_matricula
-- CREATE  TRIGGER trg_numero_matricula
--     before INSERT
--     ON libromatricula.prueba
--     FOR EACH ROW
--     EXECUTE PROCEDURE libromatricula.f_numero_matricula();






-- ---------------------------> FUNCION PARA COMPROBAR EXISTENCIA DE ID ESTUDIANTE DENTRO DEL MISMO PERIODO
-- CREATE OR REPLACE FUNCTION libromatricula.estudiante_por_periodo()
-- RETURNS TRIGGER AS $$
-- DECLARE
-- 	existe BOOLEAN;
-- BEGIN
-- 	SELECT EXISTS (
-- 		SELECT 1 FROM libromatricula.registro_matricula
-- 		WHERE id_estudiante = NEW.id_estudiante AND anio_lectivo_matricula = NEW.anio_lectivo_matricula
-- 	) INTO existe;
	
-- 	IF existe THEN
-- 		RAISE EXCEPTION 'El estudiante ya esta matriculado para el periodo escolar actual';
-- 	END IF;
	
-- 	RETURN NEW;
-- END;
-- $$ LANGUAGE plpgsql;
-- ====================================================================>>



-- ---------------------------> TRIGGER DE LA TABLA, PARA EJECUTAR LA FUNCTION
-- CREATE TRIGGER before_insert_registro_matricula
-- BEFORE INSERT ON libromatricula.registro_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.estudiante_por_periodo();
-- ====================================================================>>




-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA REGISTRAR CAMBIOS DE CURSO ============================> SIN USO

-- CREATE OR REPLACE FUNCTION libromatricula.course_change_log_function()
-- RETURNS TRIGGER AS $$

-- BEGIN
	-- IF OLD.id_curso IS NOT NULL AND NEW.id_curso <> OLD.id_curso THEN
	-- 	INSERT INTO libromatricula.course_change_log 
	-- 		(id_registro_matricula, id_old_course, old_list_number, old_assignment_date,
	-- 		 id_new_course, new_list_number, new_assignment_date, period, id_responsible_user)
	-- 	VALUES 
	-- 		(OLD.id_registro_matricula, OLD.id_curso, OLD.numero_lista_curso, OLD.fecha_alta_matricula,
	-- 		 NEW.id_curso, NEW.numero_lista_curso, NEW.fecha_alta_matricula, OLD.anio_lectivo_matricula, 
    --       NEW.id_usuario_responsable);
	-- END IF;
-- 	RETURN NEW;
-- END;

-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER DE LA FUNCIÓN PARA REGISTRAR CAMBIOS DE CURSO ============================> SIN USO

-- CREATE OR REPLACE TRIGGER course_change_log_trigger
-- AFTER UPDATE ON libromatricula.registro_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.course_change_log_function();
-- ====================================================================>>




-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA REGISTRAR BAJAS DE MATRICULA ============================> SIN USO

-- CREATE OR REPLACE FUNCTION libromatricula.registration_withdrawal_log_function()
-- RETURNS TRIGGER AS $$

-- BEGIN
	-- IF (NEW.fecha_baja_matricula IS NOT NULL AND NEW.id_estado_matricula = 4) THEN
	-- 	INSERT INTO libromatricula.registration_withdrawal_log
	-- 		(id_registro_matricula, withdrawal_date, id_responsible_user)
	-- 	VALUES
	-- 		(OLD.id_registro_matricula, NEW.fecha_baja_matricula, NEW.id_usuario_responsable);
	-- END IF;
-- 	RETURN NEW;
-- END;

-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER DE LA FUNCIÓN PARA REGISTRAR BAJAS DE MATRICULA ============================> SIN USO

-- CREATE OR REPLACE TRIGGER registration_withdrawal_log_trigger
-- AFTER UPDATE ON libromatricula.registro_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.registration_withdrawal_log_function();
-- ====================================================================>>







-- 	-- =============================================================>
-- FUNCIONES ACTUALMENTE EN USOS 
-- 	-- =============================================================>


-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA VERIFICAR ESTUDIANTE EXISTENTE POR PERIODO Y GENERACION DE NUMERO DE MATRICUAL

-- CREATE OR REPLACE FUNCTION libromatricula.set_new_matricula()
-- 	RETURNS TRIGGER AS $$
	
-- DECLARE
-- 	existe BOOLEAN;
-- 	max_number_matricula INTEGER;
-- 	grado_param INTEGER;
-- 	grado_min INTEGER;
-- 	grado_max INTEGER;
	
-- BEGIN
-- 	-- asignacion de las variables
-- 	grado_param := new.grado;
-- 	CASE 
-- 		WHEN grado_param BETWEEN 1 AND 4 THEN
-- 			grado_min := 1;
-- 			grado_max := 4;
-- 		WHEN grado_param BETWEEN 7 AND 8 THEN
-- 			grado_min := 7;
-- 			grado_max := 8;
-- 		ELSE
-- 			RAISE EXCEPTION 'Grado o periodo no válido';
-- 	END CASE;
-- 	-- =============================================================>

-- 	-- verificación de estudiante registrado en periodo actual
-- 	SELECT EXISTS (
-- 		SELECT 1 FROM libromatricula.registro_matricula
-- 		WHERE id_estudiante = NEW.id_estudiante AND anio_lectivo_matricula = NEW.anio_lectivo_matricula
-- 	) INTO existe;
	
-- 	IF existe THEN
-- 		RAISE EXCEPTION 'El estudiante ya esta matriculado para el periodo escolar actual';
-- 	END IF;
-- 	-- =============================================================>

-- 	-- generación de numero de matricula según nivel
-- 	SELECT COALESCE(MAX(numero_matricula), 0) INTO max_number_matricula
-- 	FROM libromatricula.registro_matricula
-- 	WHERE anio_lectivo_matricula = NEW.anio_lectivo_matricula
-- 	AND grado BETWEEN grado_min AND grado_max;

-- 	NEW.numero_matricula := max_number_matricula + 1;
-- 	-- =============================================================>
	
-- 	RETURN NEW;

-- END;
-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER LANZADOR DE LA FUNCION CREADA PARA EL INSERT DE UNA MATRICULA
-- CREATE OR REPLACE TRIGGER before_insert_matricula
-- 	BEFORE INSERT ON libromatricula.registro_matricula
-- 	FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.set_new_matricula();
-- ====================================================================>>




-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA ACTUALIZAR TODAS LAS FECHAS ALTA, SI SE MODIFICA LA FECHA DE INICIO DE CLASES

-- CREATE OR REPLACE FUNCTION libromatricula.update_course_assignment_date()
-- RETURNS TRIGGER AS $$
-- BEGIN
-- 	-- Condición para controlar el año lectivo
-- 	IF EXTRACT(YEAR FROM NEW.fecha_inicio_clases) <> OLD.anio_lectivo THEN
-- 		RAISE EXCEPTION 'La fecha debe ser dentro del año %', OLD.anio_lectivo;
-- 	END IF;
	
-- 	-- Condición para evitar cambio de fecha si no esta habilitado
-- 	IF NEW.fecha_inicio_clases != OLD.fecha_inicio_clases
-- 	AND OLD.permitir_modificar_fecha <> true THEN
-- 		RAISE EXCEPTION 'La fecha no puede modificarse';
-- 	END IF;

-- 	-- Condición para efectual la actualización masiva en fecha inicio clases
-- 	IF NEW.fecha_inicio_clases != OLD.fecha_inicio_clases
-- 	AND OLD.permitir_modificar_fecha = true THEN
-- 		UPDATE libromatricula.registro_matricula
-- 		SET fecha_alta_matricula = NEW.fecha_inicio_clases
-- 		WHERE id_curso IS NOT NULL
-- 		AND anio_lectivo_matricula = OLD.anio_lectivo;	
-- 	END IF;
	
-- 	RETURN NEW;
-- END;
-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER LANZADOR DE LA FUNCION CREADA PARA EL UPDATE DE FECHA INICIO CLASES

-- -- creación del trigger
-- CREATE OR REPLACE TRIGGER trigger_update_course_assignment_date
-- AFTER UPDATE ON libromatricula.periodo_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.update_course_assignment_date();
-- ====================================================================>>




-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA REGISTRAR BAJAS Y RETIROS PARA USAR EN NOMINA DE CURSOS 
-- (REEMPLAZA A LAS FUNCIONES PARA EL REGISTRO DE LOG DE CAMBIO DE CURSO Y RETIRO DE MATRICULA)

-- CREATE OR REPLACE FUNCTION libromatricula.change_course_and_withdrawal_registration_function()
-- RETURNS TRIGGER AS $$

-- BEGIN
-- 	-- registro de log, para uso en nómina de estudiantes
-- 	-- verificar si el autocorrelativo esta activado (significa el estado de las listas oficiales)
-- 	IF NOT (SELECT autocorrelativo_listas FROM libromatricula.periodo_matricula WHERE anio_lectivo = OLD.anio_lectivo_matricula) THEN

-- 		-- registro por cambio de curso
-- 		IF OLD.id_curso IS NOT NULL AND NEW.id_curso <> OLD.id_curso THEN
-- 			INSERT INTO libromatricula.student_withdrawal_from_list_log
-- 				(id_registro_matricula, id_old_course, old_number_list, discharge_date, withdrawal_date, id_responsible_user)
-- 			VALUES
-- 				(OLD.id_registro_matricula, OLD.id_curso, OLD.numero_lista_curso, OLD.fecha_alta_matricula,
-- 				 NEW.fecha_baja_matricula, NEW.id_usuario_responsable);
-- 		END IF;

-- 		-- registro por retiro de matrícula
-- 		IF NEW.fecha_retiro_matricula IS NOT NULL AND OLD.id_curso IS NOT NULL AND NEW.id_estado_matricula = 4 THEN
-- 			INSERT INTO libromatricula.student_withdrawal_from_list_log
-- 				(id_registro_matricula, id_old_course, old_number_list, discharge_date, withdrawal_date, id_responsible_user)
-- 			VALUES
-- 				(OLD.id_registro_matricula, OLD.id_curso, OLD.numero_lista_curso, OLD.fecha_alta_matricula,
-- 				 NEW.fecha_retiro_matricula, NEW.id_usuario_responsable);
-- 		END IF;
		
-- 		-- actualizar numero lista curso máximo del curso al que se asigna un estudiante
-- 		IF NEW.id_curso <> OLD.id_curso OR OLD.id_curso IS NULL THEN
-- 			UPDATE libromatricula.registro_curso
-- 			SET numero_lista_curso = NEW.numero_lista_curso
-- 			WHERE id_curso = NEW.id_curso AND periodo_escolar = OLD.anio_lectivo_matricula;
-- 		END IF;
		
-- 		-- asignar nuevo numero de lista a matricula
-- 	END IF;
	
-- 	-- registro log para los cambios de curso
-- 	IF OLD.id_curso IS NOT NULL AND NEW.id_curso <> OLD.id_curso THEN
-- 		INSERT INTO libromatricula.change_course_log 
-- 			(id_registro_matricula, id_old_course, old_list_number, withdrawal_date,
-- 			 id_new_course, new_list_number, new_assignment_date, period, id_responsible_user, old_assignment_date)
-- 		VALUES 
-- 			(OLD.id_registro_matricula, OLD.id_curso, OLD.numero_lista_curso, new.fecha_baja_matricula,
-- 			 NEW.id_curso, NEW.numero_lista_curso, NEW.fecha_alta_matricula, OLD.anio_lectivo_matricula, 
--           NEW.id_usuario_responsable, OLD.fecha_alta_matricula);
-- 	END IF;

-- 	-- registro log para los cambios de apodrado titular
-- 	IF (OLD.id_apoderado_titular IS DISTINCT FROM NEW.id_apoderado_titular) THEN
-- 		INSERT INTO libromatricula.change_representative_log
-- 			(id_registro_matricula, type_representative, id_old_representative, id_new_representative,
-- 			 period, id_responsible_user)
-- 		VALUES
-- 			(OLD.id_registro_matricula, 'TITULAR', OLD.id_apoderado_titular, NEW.id_apoderado_titular,
-- 			 OLD.anio_lectivo_matricula, NEW.id_usuario_responsable);
-- 	END IF;

-- 	-- registro log para los cambios de apoderado suplente
-- 	IF (OLD.id_apoderado_suplente IS DISTINCT FROM NEW.id_apoderado_suplente) THEN
-- 		INSERT INTO libromatricula.change_representative_log
-- 			(id_registro_matricula, type_representative, id_old_representative, id_new_representative,
-- 			 period, id_responsible_user)
-- 		VALUES
-- 			(OLD.id_registro_matricula, 'SUPLENTE', OLD.id_apoderado_suplente, NEW.id_apoderado_suplente,
-- 			 OLD.anio_lectivo_matricula, NEW.id_usuario_responsable);
-- 	END IF;
	
-- 	-- registro log para los retiros de matricula
-- 	IF (NEW.fecha_retiro_matricula IS NOT NULL AND NEW.id_estado_matricula = 4) THEN
-- 		INSERT INTO libromatricula.registration_withdrawal_log
-- 			(id_registro_matricula, withdrawal_date, id_responsible_user)
-- 		VALUES
-- 			(OLD.id_registro_matricula, NEW.fecha_retiro_matricula, NEW.id_usuario_responsable);
-- 	END IF;

	
-- 	RETURN NEW;
-- END;

-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER DE LA FUNCIÓN PARA REGISTRAR BAJAS Y RETIROS

-- CREATE OR REPLACE TRIGGER change_course_and_withdrawal_registration_trigger
-- AFTER UPDATE ON libromatricula.registro_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.change_course_and_withdrawal_registration_function();
-- ====================================================================>>




-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA ASIGNAR LOS NÚMEROS DE LISTA MÁXIMOS DE CADA CURSO

-- CREATE OR REPLACE FUNCTION libromatricula.maximum_list_number_function()
-- RETURNS TRIGGER AS $$

-- BEGIN
-- 	-- condición para generar actualización
-- 	IF NEW.autocorrelativo_listas = FALSE THEN
-- 		-- actualizar numero máximo de lista de cada curso
-- 		UPDATE libromatricula.registro_curso c
-- 		SET numero_lista_curso = (
-- 			SELECT MAX(m.numero_lista_curso)
-- 			FROM libromatricula.registro_matricula m
-- 			WHERE m.id_curso = c.id_curso AND m.anio_lectivo_matricula = OLD.anio_lectivo
-- 		)
-- 		-- permite que la actualización se aplique a todos los id_curso que esten en registro_matricula
-- 		-- y que además correspondan al año lectivo del autocorrelativo_lista que se actualiza
-- 		WHERE EXISTS (
-- 			SELECT 1
-- 			FROM libromatricula.registro_matricula m
-- 			WHERE m.id_curso = c.id_curso AND m.anio_lectivo_matricula = OLD.anio_lectivo
-- 		);
-- 	END IF;
	
-- 	RETURN NEW;
-- END;

-- $$ LANGUAGE plpgsql;
-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER DE LA FUNCIÓN PARA ASIGNAR LOS NÚMEROS DE LISTA MÁXIMOS DE CADA CURSO

-- CREATE OR REPLACE TRIGGER maximum_list_number_trigger
-- AFTER UPDATE ON libromatricula.periodo_matricula
-- FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.maximum_list_number_function();

-- --EXECUTE PROCEDURE libromatricula.maximum_list_number_function(); -> para base de datos del establecimiento
-- ====================================================================>>






-- **************************** FUNCTION ****************************
-- ---------------------------> FUNCION PARA 


-- ====================================================================>>


-- **************************** TRIGGER ****************************
-- ---------------------------> TRIGGER DE LA FUNCIÓN PARA 


-- ====================================================================>>