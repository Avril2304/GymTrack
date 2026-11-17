-- 06_gimnasio_identificador.sql
-- 1) Gimnasio.identificador: alias corto y UNICO con el que un socio encuentra su gimnasio
--    (ej. "gimnasio-central"). No da acceso a nada: solo ubica la fila para pedir unirse.
-- 2) Un admin = un gimnasio: UNIQUE sobre Gimnasio.ID_Usuario_Dueno.
-- IDEMPOTENTE. Requiere haber corrido 05_gimnasio.sql.
--
-- Si el paso 2 falla con "Duplicate entry", hay un admin duenio de mas de un gimnasio.
-- Para verlo:  SELECT ID_Usuario_Dueno, COUNT(*) FROM Gimnasio
--              WHERE ID_Usuario_Dueno IS NOT NULL GROUP BY ID_Usuario_Dueno HAVING COUNT(*) > 1;
-- Hay que resolverlo a mano (dejar un solo gimnasio por admin) y volver a correr este archivo.

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Gimnasio` ADD COLUMN `identificador` varchar(60) NULL AFTER `nombre`',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Gimnasio' AND COLUMN_NAME = 'identificador');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Los gimnasios que ya existen reciben un identificador provisorio y unico (gym-<id>).
UPDATE `Gimnasio` SET `identificador` = CONCAT('gym-', `ID_Gimnasio`) WHERE `identificador` IS NULL;

ALTER TABLE `Gimnasio` MODIFY `identificador` varchar(60) NOT NULL;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Gimnasio` ADD UNIQUE KEY `uq_gimnasio_identificador` (`identificador`)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Gimnasio' AND INDEX_NAME = 'uq_gimnasio_identificador');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Gimnasio` ADD UNIQUE KEY `uq_gimnasio_dueno` (`ID_Usuario_Dueno`)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Gimnasio' AND INDEX_NAME = 'uq_gimnasio_dueno');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
