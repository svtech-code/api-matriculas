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
--         sequence_name := 'libromatricula.secuencia_grados_1_4_' || periodo_param;
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


-- ---------------------------> TRIGGER LANZADOR DE LA FUNCION CREADA PARA EL INSERT DE UNA MATRICULA
-- CREATE OR REPLACE TRIGGER before_insert_matricula
-- 	BEFORE INSERT ON libromatricula.registro_matricula
-- 	FOR EACH ROW
-- EXECUTE FUNCTION libromatricula.set_new_matricula();
-- ====================================================================>>







