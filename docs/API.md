# API

Todos los endpoints viven en `public_html/api/` y responden JSON. Hay dos formas de llegar a ellos:

- **Archivo PHP directo** (`api/mi_gimnasio.php`).
- **Router MVC** (`backend/`): `api/.htaccess` manda ahi lo que no exista como archivo. Muchos endpoints responden con los dos nombres (por ejemplo `/perfil` y `perfil.php`).

## Identidad y permisos

- Quien llama se identifica **solo por la sesion del servidor** (cookie `PHPSESSID`, 12 horas).
- Las pantallas usan `credentials: 'same-origin'` para mandar la cookie.
- Cualquier `user_id` o `admin_id` que mande el cliente se **ignora o se pisa** con el de la sesion. Los unicos ids que se aceptan son los del objetivo de una accion de administrador (por ejemplo, a quien sumar).
- Sin sesion, las rutas con datos de usuario responden `401`. Un administrador sin gimnasio recibe `403` con `codigo: "ADMIN_SIN_GIMNASIO"`.

Formato de respuesta: `{ "ok": true|false, "msg": "...", ... }`.

## Publicos (sin sesion)

| Endpoint | Metodo | Que hace |
| --- | --- | --- |
| `login.php` | POST | Inicia sesion. Limita intentos fallidos por mail e IP y exige correo verificado si aplica |
| `register.php` | POST | Crea una cuenta de socio y envia correo de confirmacion cuando SMTP esta activo |
| `verificar_email.php` | POST | Confirma la cuenta con el `token` enviado por correo |
| `reenviar_verificacion.php` | POST | Reenvia el correo de confirmacion para una cuenta pendiente |
| `logout.php` | POST | Cierra la sesion en el servidor y borra la cookie |
| `obtener_clases.php` | GET | Catalogo de clases |
| `obtener_ejercicios.php` | GET | Catalogo de ejercicios |

## Socio

| Endpoint | Metodo | Que hace |
| --- | --- | --- |
| `perfil.php` | GET / POST | Ver y editar su perfil |
| `subir_foto_perfil.php` | POST | Cambia su foto |
| `obtener_rutinas.php`, `obtener_mis_rutinas.php`, `obtener_detalle_rutina.php` | GET | Rutinas disponibles, propias y su detalle |
| `crear_rutina.php`, `guardar_rutina_usuario.php`, `toggle_favorito.php`, `eliminar_rutina.php` | POST | Gestion de sus rutinas |
| `guardar_entrenamiento.php` | POST | Registra un entrenamiento (e inscribe la asistencia del dia) |
| `obtener_historial.php`, `eliminar_historial.php` | GET / POST | Su historial |
| `chat_coach.php` | POST | Coach con IA |
| `buscar_gimnasio.php` | GET | Encuentra un gimnasio por identificador exacto |
| `crear_solicitud.php` | POST | Pide unirse (`identificador`) |
| `mi_solicitud.php` | GET | Su gimnasio actual y su ultima solicitud |
| `cancelar_solicitud.php` | POST | Cancela su solicitud pendiente |
| `salir_gimnasio.php` | POST | Sale de su gimnasio (envia aviso por mail si SMTP esta activo) |

## Administrador

| Endpoint | Metodo | Que hace |
| --- | --- | --- |
| `crear_gimnasio.php` | POST | Crea su gimnasio (uno solo); el identificador sale del nombre si no se indica |
| `actualizar_gimnasio.php` | POST | Cambia nombre, direccion, telefono, mail o identificador de **su** gimnasio |
| `mi_gimnasio.php` | GET | Datos de su gimnasio, identificador para compartir y contadores |
| `obtener_usuarios.php` | GET | Socios de **su** gimnasio |
| `dashboard.php` | GET | Estadisticas de **su** gimnasio: socios activos (y cuantos de cuantos), altas y bajas de los ultimos 30 dias contra los 30 anteriores, y graficos |
| `solicitudes_pendientes.php` | GET | Solicitudes pendientes de su gimnasio |
| `resolver_solicitud.php` | POST | Aprueba o rechaza (`id_solicitud`, `accion`; al aprobar avisa por mail al socio) |
| `buscar_usuario.php` | GET | Busca a una persona por mail exacto |
| `asignar_gimnasio.php` | POST | Suma un usuario libre a su gimnasio, o saca a un socio suyo (envia aviso por mail) |

## Crons (`public_html/api/cron_*.php`)

Se programan desde cPanel y se protegen con el parametro `?token=` (debe coincidir con `CRON_TOKEN` de `mail_config.php`): `cron_inactividad.php` (recordatorios), `cron_resumen_mensual.php` (resumen mensual) y `cron_sync_exercisedb.php` (sincroniza ejercicios). Ver [DESPLIEGUE.md](DESPLIEGUE.md).

Los scripts de `public_html/demo/` (simulador de actividad) usan otro token, `demo_token` de `db_config.php`. Ver [DEMO.md](DEMO.md).
