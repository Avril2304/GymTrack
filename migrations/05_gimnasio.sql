-- 05_gimnasio.sql
-- Tabla Gimnasio y vinculo Usuario.ID_Gimnasio.
-- IDEMPOTENTE: se puede correr en una base que ya los tiene (produccion) o en una nueva
-- (por ejemplo, a partir de "gymtrack_tasty (2).sql", que todavia no los trae).
-- Compatible con MySQL 5.7+/8 y MariaDB 10.2+.

CREATE TABLE IF NOT EXISTS `Gimnasio` (
  `ID_Gimnasio` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `mail` varchar(60) DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL,
  `activo` enum('S','N') NOT NULL DEFAULT 'S',
  `ID_Usuario_Dueno` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`ID_Gimnasio`),
  KEY `ix_gimnasio_dueno` (`ID_Usuario_Dueno`),
  CONSTRAINT `fk_gimnasio_dueno` FOREIGN KEY (`ID_Usuario_Dueno`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario.ID_Gimnasio (nullable: NULL = socio independiente, sin gimnasio)
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Usuario` ADD COLUMN `ID_Gimnasio` int(10) UNSIGNED DEFAULT NULL',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Usuario' AND COLUMN_NAME = 'ID_Gimnasio');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Usuario` ADD KEY `ix_usuario_id_gimnasio` (`ID_Gimnasio`)',
  'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Usuario' AND INDEX_NAME = 'ix_usuario_id_gimnasio');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `Usuario` ADD CONSTRAINT `fk_usuario_gimnasio` FOREIGN KEY (`ID_Gimnasio`) REFERENCES `Gimnasio` (`ID_Gimnasio`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Usuario'
    AND CONSTRAINT_NAME = 'fk_usuario_gimnasio' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
