# Base de datos

`gymtrack_demo.sql` es la exportacion inicial de la base **con datos de demostracion**: tres usuarios ficticios (`demo1@example.com`, `demo2@example.com` y `admin@example.com`, clave `Demo1234!`) y los catalogos de clases, ejercicios y rutinas.

Que **no** trae:

- Usuarios reales: la tabla `Usuario` y las referencias en `Historial`, `Rutina` y `Usuario_RutinaDia` se reemplazaron por datos de demostracion, porque la exportacion original tenia DNI, mails, telefonos y contrasenas reales.
- La tabla `Asistencia` (migraciones 01 a 04, anteriores al repo). Es la que usan las estadisticas.
- Los cambios posteriores: despues de importarlo hay que correr las migraciones de [../migrations/](../migrations/README.md) en orden.

**Nunca exportar la base de produccion a este repositorio**: es publico.
