# GymTrack

GymTrack es una aplicacion web para gestion y seguimiento de entrenamientos. Incluye pantallas HTML para usuarios y administracion, una API en PHP y una base de datos MySQL/MariaDB.

## Estructura

- `index.html`: pantalla principal de la aplicacion.
- `login.html`: inicio de sesion y registro.
- `admin.html`: panel de administracion.
- `api/`: endpoints PHP y conexion a la base de datos.
- `img/`: imagenes usadas por la interfaz.
- `videos/ejercicios/`: videos de ejercicios usados por la app.
- `gymtrack_tasty (2).sql`: exportacion inicial de la base de datos.

## Configuracion local

1. Importar `gymtrack_tasty (2).sql` en MySQL o MariaDB.
2. Copiar `api/config.example.php` como `api/config.php`.
3. Completar `api/config.php` con los datos reales de la base de datos.
4. Servir el proyecto con Apache/PHP o subirlo a un hosting compatible con PHP.

`api/config.php` esta ignorado por Git porque contiene credenciales.

### Usuarios de prueba

El dump `gymtrack_tasty (2).sql` incluye 3 usuarios de demostracion (los datos reales de usuarios se sanitizaron antes de subir el repo):

| Mail | Password | Rol |
| --- | --- | --- |
| demo1@example.com | Demo1234! | Usuario |
| demo2@example.com | Demo1234! | Usuario |
| admin@example.com | Demo1234! | Administrador |

## Notas para GitHub

El repositorio excluye archivos de entrega academica, borradores, duplicados y fotos subidas por usuarios reales. El dump SQL fue sanitizado: la tabla `Usuario` y las referencias en `Historial`, `Rutina` y `Usuario_RutinaDia` se reemplazaron por datos de demostracion (ver tabla de arriba) porque el dump original tenia DNI, mails, telefonos y contrasenas reales de usuarios. Si se agregan nuevas credenciales, claves o archivos privados, deben quedar fuera del repositorio.
