-- 05-07_2_aplicar.sql
-- Version para hostings donde el usuario de la base NO puede leer information_schema (error #1044).
-- Hace lo mismo que las migraciones 05 + 06 + 07, pero con sentencias simples.
--
-- ANTES: hace clic en la base "gymtrack_tasty" (panel izquierdo) y corre 05-07_1_diagnostico.sql.
--        Solo corre ESTE archivo si el diagnostico dio lo esperado.
--
-- Si una sentencia falla con "Duplicate column name", "Duplicate key name" o "already exists",
-- significa que ese paso YA estaba hecho: seguí con la sentencia que sigue.
-- Cualquier otro error: sacale captura y avisame.

-- PASO 1: identificador unico para que un socio encuentre su gimnasio
ALTER TABLE `Gimnasio` ADD COLUMN `identificador` varchar(60) NULL AFTER `nombre`;

-- PASO 2: los gimnasios que ya existen reciben un identificador provisorio (gym-1, gym-2...)
UPDATE `Gimnasio` SET `identificador` = CONCAT('gym-', `ID_Gimnasio`) WHERE `identificador` IS NULL;
ALTER TABLE `Gimnasio` MODIFY `identificador` varchar(60) NOT NULL;

-- PASO 3: el identificador no se puede repetir
ALTER TABLE `Gimnasio` ADD UNIQUE KEY `uq_gimnasio_identificador` (`identificador`);

-- PASO 4: un admin = un gimnasio (si falla con "Duplicate entry", un admin tiene dos gimnasios)
ALTER TABLE `Gimnasio` ADD UNIQUE KEY `uq_gimnasio_dueno` (`ID_Usuario_Dueno`);

-- PASO 5: tabla de solicitudes para unirse a un gimnasio
CREATE TABLE IF NOT EXISTS `Solicitud` (
  `ID_Solicitud` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `ID_Gimnasio` int(10) UNSIGNED NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`ID_Solicitud`),
  KEY `ix_solicitud_gimnasio_estado` (`ID_Gimnasio`, `estado`),
  KEY `ix_solicitud_usuario_estado` (`ID_Usuario`, `estado`),
  CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitud_gimnasio` FOREIGN KEY (`ID_Gimnasio`) REFERENCES `Gimnasio` (`ID_Gimnasio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VERIFICACION: debe mostrar la columna identificador, los dos indices uq_ y la tabla Solicitud
SHOW COLUMNS FROM `Gimnasio`;
SHOW INDEX FROM `Gimnasio`;
SHOW TABLES LIKE 'Solicitud';
