-- 07_solicitud.sql
-- Solicitud de un socio para unirse a un gimnasio (el admin dueño la aprueba o rechaza).
-- IDEMPOTENTE. Requiere 05_gimnasio.sql.
--
--   pendiente  -> esperando respuesta del admin (un usuario puede tener una sola a la vez;
--                 lo garantiza la aplicacion con una transaccion, MySQL no tiene indices parciales)
--   aceptada   -> el usuario quedo vinculado (Usuario.ID_Gimnasio)
--   rechazada  -> fecha_resolucion se usa para el cooldown antes de volver a pedir a ese gimnasio
--   cancelada  -> la cancelo el propio usuario, o quedo sin efecto (ya entro a un gimnasio por otra via)

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
