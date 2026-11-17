-- 09_asistencia_unica.sql
-- Una sola asistencia por usuario y por dia: UNIQUE (ID_Usuario, fecha) en Asistencia.
--
-- Por que: el codigo que registra la asistencia (guardar_entrenamiento.php y HistorialController)
-- hace INSERT ... ON DUPLICATE KEY UPDATE hora_entrada = LEAST(...). Esa clausula solo funciona si
-- existe una clave unica; en la base real faltaba, asi que dos entrenamientos el mismo dia creaban
-- dos filas y los graficos de dias y turnos contaban de mas.
--
-- Funciona en hostings donde el usuario de la base NO puede leer information_schema (sin PREPARE).
-- Correr de a UN PASO por vez, con la base seleccionada en phpMyAdmin.
-- Si el PASO 3 falla con "Duplicate key name", la clave ya estaba creada: no hay nada mas que hacer.

-- PASO 1 (solo lectura). Debe mostrar 0 FILAS. Si muestra filas, hay asistencias repetidas: hace el PASO 2.
SELECT `ID_Usuario`, `fecha`, COUNT(*) AS filas
FROM `Asistencia`
GROUP BY `ID_Usuario`, `fecha`
HAVING COUNT(*) > 1;

-- PASO 2 (solo si el PASO 1 mostro filas). Deja la primera asistencia guardada de cada dia
-- y borra las repetidas. Con la tabla sin repetidos no hace nada.
DELETE a FROM `Asistencia` a
JOIN (
  SELECT MIN(`ID_Asistencia`) AS conservar, `ID_Usuario`, `fecha`
  FROM `Asistencia`
  GROUP BY `ID_Usuario`, `fecha`
  HAVING COUNT(*) > 1
) r ON r.`ID_Usuario` = a.`ID_Usuario` AND r.`fecha` = a.`fecha` AND a.`ID_Asistencia` <> r.conservar;

-- PASO 3: la clave unica
ALTER TABLE `Asistencia` ADD UNIQUE KEY `uq_asistencia_usuario_fecha` (`ID_Usuario`, `fecha`);

-- PASO 4 (verificacion): tiene que aparecer uq_asistencia_usuario_fecha con Non_unique = 0
SHOW INDEX FROM `Asistencia`;
