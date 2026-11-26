-- 11_verificacion_mail.sql
-- Verificacion de correo electronico al crear una cuenta nueva.
-- Agrega la columna Usuario.email_verificado (los usuarios ya existentes quedan con 'S'
-- para no bloquearles el acceso) y crea la tabla Verificacion_Email para guardar los hashes
-- SHA-256 de los tokens de confirmacion (expiran a las 24 horas).
--
-- Funciona en hostings donde el usuario de la base NO puede leer information_schema (sin PREPARE).
-- Correr ANTES de subir el codigo nuevo que usa esta columna/tabla.
-- Si el PASO 1 falla con "Duplicate column name 'email_verificado'", ya estaba aplicado: segui con el PASO 2.

-- PASO 1: columna de estado de verificacion en Usuario
ALTER TABLE `Usuario`
  ADD COLUMN `email_verificado` enum('S','N') NOT NULL DEFAULT 'S' AFTER `es_admin`;

-- PASO 2: tabla de tokens de verificacion de correo
CREATE TABLE IF NOT EXISTS `Verificacion_Email` (
  `ID_Verificacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID_Verificacion`),
  UNIQUE KEY `uq_verificacion_token_hash` (`token_hash`),
  KEY `ix_verificacion_usuario` (`ID_Usuario`, `usado_en`),
  CONSTRAINT `fk_verificacion_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VERIFICACION: tiene que aparecer la columna email_verificado y la tabla Verificacion_Email
SHOW COLUMNS FROM `Usuario` LIKE 'email_verificado';
SHOW TABLES LIKE 'Verificacion_Email';
