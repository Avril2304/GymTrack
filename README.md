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

### Migraciones

Despues de importar el dump, correr en orden los archivos de `migrations/` (son idempotentes: se pueden repetir, y en una base que ya tiene los cambios no hacen nada):

| Archivo | Que hace |
| --- | --- |
| `05_gimnasio.sql` | Tabla `Gimnasio` y columna `Usuario.ID_Gimnasio` (con indices y claves foraneas) |
| `06_gimnasio_identificador.sql` | `Gimnasio.identificador` unico y un admin = un gimnasio (`UNIQUE` sobre el duenio) |
| `07_solicitud.sql` | Tabla `Solicitud` (pendiente / aceptada / rechazada / cancelada) |

**Orden de despliegue:** primero las migraciones, despues el codigo. `asignar_gimnasio` y el circuito de solicitudes dependen de la tabla `Solicitud`. Las migraciones 01 a 04 (asistencia) no estan en este repo.

## Gimnasios y alta de socios

Un admin es duenio de **un** gimnasio; un socio pertenece a **uno** o a ninguno (`Usuario.ID_Gimnasio` nulo = independiente). El admin solo ve y administra a los socios de su gimnasio. Un admin sin gimnasio no ve datos de nadie hasta que crea el suyo.

Todos los endpoints identifican a quien llama por la **sesion del servidor** (`api/sesion.php`); ningun id que mande el cliente se usa para decidir permisos. Las reglas viven en `api/gimnasio_lib.php` y las comparten los endpoints PHP y los controladores de `backend/`.

| Quien | Endpoint (`api/`) | Metodo | Que hace |
| --- | --- | --- | --- |
| Admin | `crear_gimnasio.php` | POST | Crea su gimnasio (uno solo); el identificador sale del nombre si no se indica |
| Admin | `mi_gimnasio.php` | GET | Datos de su gimnasio, identificador para compartir y contadores |
| Admin | `solicitudes_pendientes.php` | GET | Solicitudes pendientes de su gimnasio |
| Admin | `resolver_solicitud.php` | POST | Aprueba o rechaza (`id_solicitud`, `accion`) |
| Admin | `buscar_usuario.php` | GET | Busca a una persona por mail exacto para sumarla directo |
| Admin | `asignar_gimnasio.php` | POST | Suma un usuario libre a su gimnasio, o saca a un socio suyo |
| Socio | `buscar_gimnasio.php` | GET | Encuentra un gimnasio por identificador exacto (sin listado ni busqueda parcial) |
| Socio | `crear_solicitud.php` | POST | Pide unirse (`identificador`) |
| Socio | `mi_solicitud.php` | GET | Su gimnasio actual y su ultima solicitud |
| Socio | `cancelar_solicitud.php` | POST | Cancela su solicitud pendiente |
| Socio | `salir_gimnasio.php` | POST | Sale de su gimnasio |

Reglas del alta: una sola solicitud pendiente por usuario; quien ya tiene gimnasio no puede pedir otro (primero sale); los administradores no se unen como socios; tras un rechazo hay una espera de 7 dias para volver a pedir a ese mismo gimnasio (`GT_COOLDOWN_DIAS`); la busqueda de gimnasios y de usuarios tiene un limite de intentos por minuto. Las operaciones que modifican datos solo aceptan POST.

### Usuarios de prueba

El dump `gymtrack_tasty (2).sql` incluye 3 usuarios de demostracion (los datos reales de usuarios se sanitizaron antes de subir el repo):

| Mail | Password | Rol |
| --- | --- | --- |
| demo1@example.com | Demo1234! | Usuario |
| demo2@example.com | Demo1234! | Usuario |
| admin@example.com | Demo1234! | Administrador |

## Notas para GitHub

El repositorio excluye archivos de entrega academica, borradores, duplicados, logs del servidor, GIFs generados y fotos subidas por usuarios reales. El dump SQL fue sanitizado: la tabla `Usuario` y las referencias en `Historial`, `Rutina` y `Usuario_RutinaDia` se reemplazaron por datos de demostracion porque el dump original tenia DNI, mails, telefonos y contrasenas reales de usuarios. Si se agregan nuevas credenciales, claves o archivos privados, deben quedar fuera del repositorio. No exportar la base de produccion al repo.
