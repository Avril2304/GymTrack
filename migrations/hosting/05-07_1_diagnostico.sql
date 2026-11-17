-- 05-07_1_diagnostico.sql  (SOLO LECTURA: no modifica nada)
-- Hace clic en la base "gymtrack_tasty" (panel izquierdo), pestana SQL, pega esto y "Continuar".
-- Cada consulta muestra su resultado en una tabla aparte (desplazate hacia abajo para verlas todas).

-- (A) Debe mostrar 1 FILA: la columna ID_Gimnasio ya existe en Usuario
SHOW COLUMNS FROM `Usuario` LIKE 'ID_Gimnasio';

-- (B) Debe mostrar 0 FILAS: la columna identificador todavia no existe en Gimnasio
SHOW COLUMNS FROM `Gimnasio` LIKE 'identificador';

-- (C) Debe mostrar 0 FILAS: la tabla Solicitud todavia no existe
SHOW TABLES LIKE 'Solicitud';

-- (D) Debe mostrar "InnoDB" en la columna Engine, para las dos tablas
SHOW TABLE STATUS WHERE `Name` IN ('Usuario', 'Gimnasio');

-- (E) Debe mostrar 0 FILAS: ningun admin es duenio de mas de un gimnasio
SELECT `ID_Usuario_Dueno`, COUNT(*) AS gimnasios
FROM `Gimnasio` WHERE `ID_Usuario_Dueno` IS NOT NULL
GROUP BY `ID_Usuario_Dueno` HAVING COUNT(*) > 1;
