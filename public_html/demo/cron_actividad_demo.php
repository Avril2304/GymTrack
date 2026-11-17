<?php
// demo/cron_actividad_demo.php - Carga actividad demo para que el dashboard tenga datos reales.
// Reglas y marcas de los datos demo: ver demo_lib.php. Borrar todo lo demo: reset_demo.php.
//
// MODOS (todos piden ?token=  =  db_config.php['demo_token'])
//   ?modo=estado                 Solo lectura: gimnasios encontrados y cuanto hay cargado.
//   ?modo=inicial&mes=2025-03    Carga inicial de UN mes, dia por dia, hasta AYER como maximo.
//                                Crea los 5 gimnasios demo y sus admins la primera vez.
//                                Se corre mes por mes (2025-03 ... mes actual); al final dice cual sigue.
//   ?modo=diario                 Lo llama el Cron Job de cPanel cada noche: carga HOY.
//                                Despues del 14/12/2026 no hace nada. No crea gimnasios ni admins.
//                                Ademas simula a los socios demo que deciden irse: los que llevan
//                                4 meses sin venir (DEMO_DIAS_BAJA) y unos pocos activos por mes
//                                (DEMO_SALIDAS_MES). Quedan sin gimnasio y se anota su baja.
//   ?modo=diario&fecha=AAAA-MM-DD  Recupera un dia que el cron no corrio (no acepta fechas futuras).
//
// Desde la terminal tambien funciona: php cron_actividad_demo.php modo=diario token=...
// En consola (CLI) se acepta ademas mes=todo para cargar todo el historial de una vez.
//
// Correrlo dos veces con los mismos parametros no duplica nada.
//
// Si se rehace la carga inicial (despues de reset_demo.php), correr al final
// demo/cargar_movimientos.php para armar las altas y bajas del pasado.

set_time_limit(300);
ignore_user_abort(true);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../api/conexion.php';   // $pdo y $dbConfig (lee api/db_config.php)
require_once __DIR__ . '/demo_lib.php';

demo_parametros();
demo_verificar_token($dbConfig);
demo_bloquear($pdo);

$modo = isset($_GET['modo']) ? (string) $_GET['modo'] : '';
$hoy = demo_hoy();
$ayer = demo_sumar_dias($hoy, -1);

echo "=== Simulador de actividad demo === " . date('Y-m-d H:i:s') . " (hora Argentina)\n\n";

$avisos = [];
$ids = demo_resolver_gimnasios($pdo, $dbConfig, $avisos);

// ---------------------------------------------------------------- estado
if ($modo === 'estado') {
    $st = $pdo->prepare("
        SELECT COUNT(*) AS socios, MIN(fecha_alta) AS primera, MAX(fecha_alta) AS ultima
        FROM Usuario WHERE ID_Gimnasio = ? AND mail LIKE ? AND LENGTH(DNI) = 8 AND DNI BETWEEN ? AND ?
    ");
    $stA = $pdo->prepare("SELECT COUNT(*) AS n, MAX(fecha) AS ultima FROM Asistencia WHERE ID_Gimnasio = ?");
    foreach (demo_gimnasios() as $g => $cfg) {
        $nombre = $g === 0 ? 'Gimnasio actual' : $cfg['nombre'];
        if (!isset($ids[$g])) {
            echo str_pad($nombre, 20) . " (todavia no existe)\n";
            continue;
        }
        $st->execute([$ids[$g], '%' . DEMO_DOMINIO, (string) DEMO_DNI_SOCIO_MIN, (string) DEMO_DNI_SOCIO_MAX]);
        $s = $st->fetch();
        $stA->execute([$ids[$g]]);
        $a = $stA->fetch();
        echo str_pad($nombre, 20) . " ID {$ids[$g]} | socios demo: {$s['socios']} (altas {$s['primera']} a {$s['ultima']})"
           . " | asistencias: {$a['n']} (ultima {$a['ultima']})\n";
    }
    foreach ($avisos as $av) {
        echo "[AVISO] $av\n";
    }
    exit(0);
}

// ---------------------------------------------------------------- rango de fechas a cargar
if ($modo === 'inicial') {
    $mes = isset($_GET['mes']) ? (string) $_GET['mes'] : '';
    if ($mes === 'todo') {
        if (PHP_SAPI !== 'cli') {
            demo_salir(400, 'mes=todo solo se acepta desde la terminal. En el navegador, de a un mes: mes=' . substr(DEMO_INICIO, 0, 7));
        }
        $desde = DEMO_INICIO;
        $hasta = $ayer;
    } elseif (preg_match('/^\d{4}-\d{2}$/', $mes) && demo_fecha_valida("$mes-01")) {
        $desde = max(DEMO_INICIO, "$mes-01");
        $hasta = min(date('Y-m-t', strtotime("$mes-01 12:00:00")), $ayer);
    } else {
        demo_salir(400, 'Falta mes=AAAA-MM. La carga empieza en mes=' . substr(DEMO_INICIO, 0, 7) . '.');
    }
    if ($desde > $hasta) {
        demo_salir(400, "No hay dias para cargar en ese rango: la carga inicial va del " . DEMO_INICIO . " hasta ayer ($ayer).");
    }

    $log = [];
    $ids = demo_crear_gimnasios($pdo, $dbConfig, $ids, $log);
    foreach ($log as $l) {
        echo "$l\n";
    }
} elseif ($modo === 'diario') {
    $fecha = isset($_GET['fecha']) ? (string) $_GET['fecha'] : $hoy;
    if (!demo_fecha_valida($fecha) || $fecha > $hoy || $fecha < DEMO_INICIO) {
        demo_salir(400, "fecha invalida: tiene que ser entre " . DEMO_INICIO . " y hoy ($hoy).");
    }
    if ($fecha > DEMO_FIN_CRON) {
        demo_salir(200, "Cron apagado: el simulador cargo hasta el " . DEMO_FIN_CRON . ". Ya podés borrar el Cron Job en cPanel.");
    }
    $desde = $hasta = $fecha;
} else {
    demo_salir(400, 'Falta modo: estado, inicial o diario.');
}

foreach ($avisos as $av) {
    echo "[AVISO] $av\n";
}
if (!$ids) {
    demo_salir(500, 'No hay gimnasios demo para cargar.');
}

// ---------------------------------------------------------------- simulacion dia por dia
try {
    $ctx = demo_contexto($pdo, $ids);
    $sts = demo_sentencias($pdo);
    $gyms = demo_gimnasios();
    $total = [];

    echo "\nCargando del $desde al $hasta" . ($modo === 'diario' ? ' (cron diario)' : '') . "\n";
    echo str_repeat('-', 60) . "\n";
    for ($f = $desde; $f <= $hasta; $f = demo_sumar_dias($f, 1)) {
        $res = demo_procesar_dia($pdo, $ctx, $sts, $f, $modo === 'diario');
        $linea = [];
        foreach ($res as $g => $r) {
            foreach ($r as $k => $v) {
                $total[$g][$k] = (isset($total[$g][$k]) ? $total[$g][$k] : 0) + $v;
            }
            if ($modo === 'diario') {
                $nombre = $g === 0 ? 'Gimnasio actual' : $gyms[$g]['nombre'];
                $linea[] = str_pad($nombre, 20) . "socios nuevos {$r['altas']} | bajas {$r['bajas']} | asistencias {$r['asistencias']} | ejercicios {$r['historial']}";
            }
        }
        if ($modo === 'diario') {
            echo "$f\n  " . implode("\n  ", $linea) . "\n";
        } elseif (substr($f, 8, 2) === '01' || $f === $desde) {
            echo "  $f ...\n";
        }
    }

    echo str_repeat('-', 60) . "\n";
    echo "RESUMEN (filas nuevas; 0 = ya estaban cargadas)\n";
    foreach ($total as $g => $r) {
        $nombre = $g === 0 ? 'Gimnasio actual' : $gyms[$g]['nombre'];
        echo '  ' . str_pad($nombre, 20) . "socios nuevos {$r['altas']} | bajas {$r['bajas']} | asistencias {$r['asistencias']} | ejercicios {$r['historial']}\n";
    }

    if ($modo === 'inicial') {
        if ($hasta >= $ayer) {
            echo "\nCarga inicial completa hasta ayer ($ayer).\n"
               . "Esta noche ya puede correr el cron diario (modo=diario), que carga hoy ($hoy).\n";
        } else {
            $sig = date('Y-m', strtotime(substr($hasta, 0, 7) . '-01 12:00:00 +1 month'));
            echo "\nSiguiente tanda: modo=inicial&mes=$sig\n";
        }
    }
    echo "FIN: " . date('Y-m-d H:i:s') . "\n";
} catch (Throwable $e) {
    error_log('cron_actividad_demo: ' . $e->getMessage());
    demo_salir(500, "[ERROR] Se corto en el dia " . (isset($f) ? $f : '?') . ' (ese dia no quedo guardado): ' . $e->getMessage());
}
