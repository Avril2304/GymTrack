# GymTrack

Aplicacion web para gestion y seguimiento de entrenamientos. Cada **gimnasio** tiene un **administrador** (su duenio) y sus **socios**; los socios registran rutinas y entrenamientos, y el administrador ve las estadisticas de su propio gimnasio.

Stack: pantallas HTML (Bootstrap) + API en PHP (endpoints directos y una capa MVC con mini-ORM estilo Eloquent) + MySQL/MariaDB. Sin `vendor/` ni Composer: corre tal cual en un hosting compartido (cPanel).

## Documentacion

| Documento | Para que sirve |
| --- | --- |
| [docs/ROLES.md](docs/ROLES.md) | Roles (socio, admin), como un socio se une a un gimnasio y reglas del alta |
| [docs/API.md](docs/API.md) | Todos los endpoints, quien puede llamarlos y como se identifica al usuario |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Como subir el proyecto a cPanel y actualizar la base sin romper nada |
| [migrations/README.md](migrations/README.md) | Cambios de base de datos, orden y cuales correr en cada entorno |
| [database/README.md](database/README.md) | Que contiene el dump de demostracion |

## Estructura

La estructura del repo replica la del hosting: `public_html/` y `backend/` quedan **una al lado de la otra**.

```
GymTrack/
|-- public_html/              Lo que se publica en la web
|   |-- index.html            App del socio (rutinas, entrenamientos, perfil, coach IA, gimnasio)
|   |-- login.html            Ingreso y registro
|   |-- admin.html            Panel del administrador (gimnasio, socios, solicitudes)
|   |-- estadisticas.html     Estadisticas del gimnasio del administrador
|   |-- api/                  Endpoints PHP y plantillas de configuracion (*.example.php)
|   |-- img/, videos/         Recursos de la interfaz
|-- backend/                  Capa MVC: router, controladores y modelos (fuera de la web publica)
|-- migrations/               Cambios de base de datos, en orden numerado (+ variantes para hosting)
|-- database/                 Dump de demostracion de la base
|-- docs/                     Documentacion
```

`api/.htaccess` manda a `backend/` todo lo que no sea un archivo real; los archivos PHP sueltos tienen prioridad sobre las rutas del router con el mismo nombre.

## Puesta en marcha local

1. Crear una base y **importar** `database/gymtrack_demo.sql`.
2. Correr en orden las migraciones de `migrations/` (05 en adelante). Ver [migrations/README.md](migrations/README.md).
3. Copiar cada plantilla y completar los datos:

   | Plantilla | Archivo real (ignorado por Git) | Contiene |
   | --- | --- | --- |
   | `public_html/api/db_config.example.php` | `public_html/api/db_config.php` | Base de datos (endpoints PHP) |
   | `backend/.env.example` | `backend/.env` | Base de datos (capa MVC) |
   | `public_html/api/config.example.php` | `public_html/api/config.php` | Claves de OpenAI y RapidAPI |
   | `public_html/api/mail_config.example.php` | `public_html/api/mail_config.php` | SMTP y token de los crons |

4. Servir `public_html/` con Apache + PHP 8 (con `mod_rewrite`) y abrir `login.html`.

Los archivos reales de configuracion **no estan en Git** (tienen contrasenas): al subir codigo no se pisan, pero tienen que existir en el servidor.

### Usuarios de prueba

| Mail | Password | Rol |
| --- | --- | --- |
| demo1@example.com | Demo1234! | Socio |
| demo2@example.com | Demo1234! | Socio |
| admin@example.com | Demo1234! | Administrador |

## Seguridad en pocas lineas

- La identidad sale **siempre de la sesion del servidor**; ningun `user_id` o `admin_id` que mande el cliente se usa para decidir permisos.
- Sesion de 12 horas, cookie `HttpOnly`, `SameSite=Lax` (y `Secure` con HTTPS), guardada en una carpeta propia fuera de la web.
- Login con limite de intentos fallidos (por mail e IP) y cierre de sesion real (`logout.php`).
- Un administrador solo ve y modifica a los socios de **su** gimnasio.
- El repositorio es **publico**: nunca subir contrasenas, claves, tokens, logs del servidor ni exportaciones de la base real. El dump de `database/` es de demostracion.

## Equipo

Proyecto de Laboratorio II (UBP). Cada cambio nuevo en la base va como migracion numerada en `migrations/` y se documenta ahi.
