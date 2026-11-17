# Migraciones

Cambios de base de datos, en orden numerado. Cada archivo explica en su cabecera que hace y por que.

| Archivo | Que hace | Hosting (cPanel) |
| --- | --- | --- |
| `05_gimnasio.sql` | Tabla `Gimnasio` y columna `Usuario.ID_Gimnasio` (indices y claves foraneas) | Aplicada (variante `hosting/`) |
| `06_gimnasio_identificador.sql` | `Gimnasio.identificador` unico y un admin = un gimnasio | Aplicada (variante `hosting/`) |
| `07_solicitud.sql` | Tabla `Solicitud` (pendiente / aceptada / rechazada / cancelada) | Aplicada (variante `hosting/`) |
| `08_login_intentos.sql` | Tabla `login_intentos` para limitar intentos fallidos de login | Aplicada |
| `09_asistencia_unica.sql` | `UNIQUE (ID_Usuario, fecha)` en `Asistencia`: una asistencia por dia | Aplicada |

Las migraciones 01 a 04 (tablas de asistencia) **no estan en este repo**: ya estaban aplicadas en la base real antes de empezar a versionar. Por eso el dump de `database/` no trae la tabla `Asistencia`; las estadisticas la necesitan.

## Reglas

- **Numerar en orden** y no repetir numeros. Antes de crear una nueva, hacer `git pull` y mirar cual es la ultima.
- Cada migracion nueva debe poder **repetirse sin romper nada**, o dejar escrito en la cabecera como verificarla.
- Se corre **primero la migracion y despues el codigo** que la usa.

## En cada entorno

**Local (XAMPP):** importar `database/gymtrack_demo.sql` y correr `05` a `09` en orden. Las `05` a `07` usan `information_schema` y piden permisos que en local si hay.

**Hosting (cPanel):** el usuario de la base **no puede leer `information_schema`**, asi que `05` a `07` fallan con el error #1044. Para una base nueva usar la variante de `hosting/`:

1. `hosting/05-07_1_diagnostico.sql`: solo lee, dice si la base esta lista.
2. `hosting/05-07_2_aplicar.sql`: aplica `05` a `07` con sentencias simples. Correr **de a una sentencia**: si alguna dice "Duplicate column name", "Duplicate key name" o "already exists", ese paso ya estaba hecho.

`08` y `09` ya estan escritas sin `information_schema`: se pueden pegar tal cual en phpMyAdmin.

> En la base real de produccion `05` a `09` ya estan aplicadas. No volver a correrlas.
