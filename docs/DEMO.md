# Datos de demostracion

Para la presentacion (30/11/2026) la base real tiene gimnasios, socios y entrenamientos **simulados**, asi las estadisticas muestran datos. Todo vive en `public_html/demo/` y se puede borrar sin tocar los datos reales.

## Como se reconocen los datos demo

- **Socios:** mail `@example.com` y DNI de 8 cifras entre 90.000.000 y 95.999.999.
- **Administradores demo:** mail `@example.com` y DNI 99.000.000 + n. Son los duenios de los gimnasios simulados (por ejemplo `examen@example.com`, que figura como Laura Benitez).
- Ningun usuario real queda alcanzado por esas reglas. Los socios demo **no pueden iniciar sesion** (su clave es aleatoria). Los administradores demo entran con la clave `demo_clave_admin` de `db_config.php`.

## Configuracion (en `db_config.php`, que no se versiona)

| Clave | Para que sirve |
| --- | --- |
| `demo_token` | Token de 16 caracteres o mas. Sin el, todos los scripts responden 403 |
| `demo_clave_admin` | Clave (8 o mas caracteres) de los administradores demo |
| `demo_gimnasio_actual` | Identificador del gimnasio real, que la demo respeta |
| `limpiar_conservar` | Lista de mails que `limpiar_base.php` no borra |

Todos los scripts se llaman con `?token=...` (o por consola: `php script.php token=...`). Sin `confirmar=SI` solo muestran lo que harian.

## Scripts

| Script | Que hace |
| --- | --- |
| `cron_actividad_demo.php?modo=estado` | Solo lectura: gimnasios encontrados y datos cargados |
| `cron_actividad_demo.php?modo=inicial&mes=AAAA-MM` | Carga un mes de actividad, dia por dia. Se corre mes por mes |
| `cron_actividad_demo.php?modo=diario` | Lo llama el Cron Job de cPanel cada noche: carga el dia. **Despues del 14/12/2026 no hace nada** |
| `cargar_movimientos.php` | Arma una sola vez las altas y bajas del pasado (`Movimiento_Socio`) |
| `reset_demo.php` | Borra todo lo que cargo el simulador |
| `limpiar_base.php` | **Destructivo, de un solo uso.** Deja la base limpia antes de cargar la demo. Borrarlo del hosting despues de usarlo |

Correr dos veces lo mismo no duplica nada. **Antes de cualquier script destructivo, exportar la base desde phpMyAdmin.**

## Altas y bajas

El dashboard cuenta "Socios nuevos" y "Bajas" desde `Movimiento_Socio` ([migracion 10](../migrations/10_movimiento_socio.sql)). Las registra el codigo real cuando un administrador acepta una solicitud, vincula o desvincula a un socio, o cuando el socio sale del gimnasio. En el sistema real no hay bajas automaticas. Solo el simulador da de baja a socios demo (origen `simulador`).

## Cron en cPanel

Mismo formato que los demas crons de [DESPLIEGUE.md](DESPLIEGUE.md), una vez por noche:

```
curl -s "https://<tu-dominio>/demo/cron_actividad_demo.php?modo=diario&token=<DEMO_TOKEN>" > /dev/null
```

**Despues de la entrega (14/12/2026): borrar ese Cron Job y la carpeta `public_html/demo/` del hosting.** No dejar scripts de borrado en un servidor publico.
