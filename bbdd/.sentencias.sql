-- -> función para obtener el número de matrícula correlativo
-- -> function to obtain the correlative registration number

-- --------------------------> SENTENCIA

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