# GymTrack

GymTrack es una aplicacion web para gestion y seguimiento de entrenamientos. Incluye pantallas HTML para usuarios y administracion, una API en PHP, una capa MVC con un mini-ORM estilo Eloquent y una base de datos MySQL/MariaDB.

## Estructura

La estructura del repo replica la del hosting (cPanel):

- `public_html/`: lo que se publica en la raiz web.
  - `index.html`, `login.html`, `admin.html`, `estadisticas.html`: pantallas.
  - `api/`: endpoints PHP directos (login, usuarios, gimnasios, dashboard, coach IA, crons). Lo que no exista como archivo lo resuelve `backend/` via `api/.htaccess`.
  - `img/`, `videos/ejercicios/`: recursos usados por la interfaz.
- `backend/`: capa MVC (controladores, modelos y router) con un mini-ORM propio, sin `vendor/`.
- `gymtrack_tasty (2).sql`: exportacion inicial de la base de datos (con usuarios de demostracion).

## Configuracion local

1. Importar `gymtrack_tasty (2).sql` en MySQL o MariaDB.
2. Copiar y completar cada archivo de configuracion a partir de su plantilla:

   | Plantilla | Archivo real (ignorado por Git) | Contiene |
   | --- | --- | --- |
   | `public_html/api/db_config.example.php` | `public_html/api/db_config.php` | Credenciales de la base (endpoints PHP) |
   | `public_html/api/config.example.php` | `public_html/api/config.php` | Claves de OpenAI y RapidAPI |
   | `public_html/api/mail_config.example.php` | `public_html/api/mail_config.php` | SMTP y token de los crons |
   | `backend/.env.example` | `backend/.env` | Credenciales de la base (capa MVC) |

3. Servir `public_html/` con Apache/PHP (el `backend/` debe quedar al lado de `public_html/`).

**Al desplegar:** estos archivos reales no estan en Git, asi que subir el codigo no los pisa, pero tienen que existir en el servidor. En particular `db_config.php` y `backend/.env` son obligatorios con esta version: ya no hay credenciales escritas en el codigo.

### Usuarios de prueba

El dump `gymtrack_tasty (2).sql` incluye 3 usuarios de demostracion (los datos reales de usuarios se sanitizaron antes de subir el repo):

| Mail | Password | Rol |
| --- | --- | --- |
| demo1@example.com | Demo1234! | Usuario |
| demo2@example.com | Demo1234! | Usuario |
| admin@example.com | Demo1234! | Administrador |

## Notas para GitHub

El repositorio excluye archivos de entrega academica, borradores, duplicados, logs del servidor, GIFs generados y fotos subidas por usuarios reales. El dump SQL fue sanitizado: la tabla `Usuario` y las referencias en `Historial`, `Rutina` y `Usuario_RutinaDia` se reemplazaron por datos de demostracion porque el dump original tenia DNI, mails, telefonos y contrasenas reales de usuarios. Si se agregan nuevas credenciales, claves o archivos privados, deben quedar fuera del repositorio. No exportar la base de produccion al repo.
