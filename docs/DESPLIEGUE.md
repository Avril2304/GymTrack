# Despliegue en cPanel

## Como queda el hosting

`public_html/` y `backend/` son **hermanas** dentro de la carpeta del usuario (`<home>`):

```
<home>/
|-- public_html/        <- se publica en la web (contenido de public_html/ del repo)
|-- backend/            <- NO se publica (contenido de backend/ del repo)
|-- sessions/           <- la crea el codigo solo; guarda las sesiones de 12 horas
```

Si `backend/` quedara dentro de `public_html/`, el codigo del servidor quedaria expuesto.

## Archivos que no estan en Git y tienen que existir

| Archivo en el servidor | Plantilla | Contiene |
| --- | --- | --- |
| `public_html/api/db_config.php` | `db_config.example.php` | Base de datos (endpoints PHP) |
| `backend/.env` | `backend/.env.example` | Base de datos (capa MVC) |
| `public_html/api/config.php` | `config.example.php` | Claves de OpenAI y RapidAPI |
| `public_html/api/mail_config.php` | `mail_config.example.php` | SMTP y token de los crons |

Subir codigo **no** los pisa. Cuidado al subir un zip que los incluya, porque ahi si se reemplazarian por los de otra maquina.

## Orden para actualizar

1. **Respaldo**: exportar la base desde phpMyAdmin (pestana Exportar) y descargar los HTML actuales.
2. **Base de datos primero**: aplicar las migraciones nuevas ([../migrations/README.md](../migrations/README.md)). Correrlas **de a una sentencia** y mirar el resultado.
3. **Despues el codigo**: subir `public_html/` y `backend/` con el Administrador de archivos (Cargar, aceptar reemplazar). Los HTML van en `public_html/`, no dentro de `api/`.
4. **Probar**:
   - Registrarse con un correo y DNI nuevos.
   - Cerrar sesion y volver a entrar.
   - Como administrador: crear el gimnasio y abrir el panel.
   - Como socio: buscar el gimnasio por identificador y mandar la solicitud.

## Notas del hosting

- El usuario de la base **no puede leer `information_schema`** (error #1044 en phpMyAdmin). Por eso hay variantes sin ese acceso en `migrations/hosting/`.
- Los errores del servidor quedan en `error_log` (dentro de `public_html/api/` o de `backend/`). Es la primera pista si algo falla; el navegador solo muestra "Error de conexion".
- `error_log`, `.env` y los `*_config.php` responden 403 desde la web. Si dejaran de hacerlo, avisar.
- No dejar en `public_html/` material que no sea de GymTrack (trabajos practicos, pruebas, copias): todo lo que este ahi es publico.

## Crons

Se crean en cPanel, seccion **Cron Jobs**. Cada script se llama por HTTP y exige el parametro `token`, que tiene que coincidir con `CRON_TOKEN` de `mail_config.php` (sin token responde 403). Ejemplo de comando, con el token real en lugar del marcador:

```
curl -s "https://<tu-dominio>/api/cron_inactividad.php?token=<CRON_TOKEN>" > /dev/null
```

`cron_inactividad.php` esta pensado para correr una vez por dia (por la manana). El token **no se escribe en el repo**: queda solo en `mail_config.php` y en la linea del cron de cPanel. Comprobar la hora del servidor: si no es la de Argentina, el script debe fijar la zona horaria.
