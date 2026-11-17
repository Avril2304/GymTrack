-- 10_movimiento_socio.sql
-- Historial de altas y bajas de socios en cada gimnasio. Lo usa el dashboard para contar
-- "Socios nuevos" y "Bajas" de los ultimos 30 dias.
-- IDEMPOTENTE. Requiere 05_gimnasio.sql. Correr ANTES de subir los PHP que escriben en esta tabla.
--
--   tipo    alta  -> el socio entro al gimnasio
--           baja  -> el socio dejo el gimnasio (Usuario.ID_Gimnasio quedo en NULL)
--
--   origen  solicitud      -> el admin acepto su solicitud (gimnasio_lib.php: gt_solicitud_resolver)
--           admin          -> el admin lo vinculo o lo desvinculo (asignar_gimnasio.php / GimnasioController)
--           socio          -> el socio decidio irse (gimnasio_lib.php: gt_usuario_salir)
--           carga_inicial  -> alta de los socios que ya estaban antes de crear esta tabla
--           simulador      -> movimientos de los datos demo (demo/), nunca del uso real
--
-- En el sistema real no hay bajas automaticas: un socio solo deja el gimnasio si el decide
-- irse o si el admin lo desvincula.

CREATE TABLE IF NOT EXISTS `Movimiento_Socio` (
  `ID_Movimiento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `ID_Gimnasio` int(10) UNSIGNED NOT NULL,
  `tipo` enum('alta','baja') NOT NULL,
  `origen` enum('solicitud','admin','socio','carga_inicial','simulador') NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID_Movimiento`),
  KEY `ix_movimiento_gimnasio_tipo_fecha` (`ID_Gimnasio`, `tipo`, `fecha`),
  KEY `ix_movimiento_usuario_fecha` (`ID_Usuario`, `fecha`),
  CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_movimiento_gimnasio` FOREIGN KEY (`ID_Gimnasio`) REFERENCES `Gimnasio` (`ID_Gimnasio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
