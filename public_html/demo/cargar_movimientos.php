<?php
// demo/cargar_movimientos.php - Arma UNA VEZ el historial de altas y bajas (Movimiento_Socio)
// sobre los datos que ya estaban cargados. Requiere migrations/10_movimiento_socio.sql.
//
//   ?token=...                 Solo muestra lo que haria (no toca nada).
//   ?token=...&confirmar=SI    Aplica los cambios.   (token = db_config.php['demo_token'])
//   Desde la terminal: php cargar_movimientos.php token=... confirmar=SI
//
// PASOS
//  1. Alta 'carga_inicial' de cada socio que hoy tiene gimnasio y todavia no tiene ninguna alta
//     (fecha = su fecha de alta). Incluye a los usuarios reales, no solo a los demo.
//  2. Socios DEMO que llevan DEMO_DIAS_BAJA dias (4 meses) o mas sin entrenar: "deciden irse" el dia
//     en que cumplieron los 4 meses sin venir. Quedan sin gimnasio y se anota su baja ('simulador').
//     Es la misma regla que aplica el cron diario desde ahora. Los usuarios reales no se tocan.
//  3. Socios DEMO extra que se fueron en el ultimo mes estando activos (DEMO_EXTRA_BAJAS), con
//     historial generado por el simulador, para que "Bajas" se note en el dashboard de la demo.
//
// Correrlo de nuevo no duplica nada. Para deshacer todo lo demo: reset_demo.php.
// HACER ANTES UN RESPALDO DESDE phpMyAdmin.

set_time_limit(300);
ignore_user_abort(true);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../api/conexion.php';   // $pdo y $dbConfig (lee api/db_config.php)
require_once __DIR__ . '/demo_lib.php';

// Socios extra que se dan de baja en los ultimos 30 dias, por gimnasio (clave de demo_gimnasios()).
// Para que se note mas o menos en la presentacion, cambiar solo este numero.
define('DEMO_EXTRA_BAJAS', ['examen' => 5]);
// Posicion del DNI que usan los socios extra. El simulador nunca la usa: crea a lo sumo 3 socios
// por dia (posiciones 0 a 2) y 15 fundadores el dia de apertura (0 a 14).
define('DEMO_K_EXTRA', 19);

demo_parametros();
demo_verificar_token($dbConfig);
demo_bloquear($pdo);

$aplicar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'SI';
$hoy = demo_hoy();

echo "=== Carga de altas y bajas (Movimiento_Socio) === " . date('Y-m-d H:i:s') . " (hora Argentina)\n";
echo $aplicar ? "MODO: aplica los cambios\n\n" : "MODO: solo muestra (para aplicar: confirmar=SI)\n\n";

$existe = $pdo->query("SHOW TABLES LIKE 'Movimiento_Socio'")->fetch();
if (!$existe) {
    demo_salir(500, 'Falta la tabla Movimiento_Socio: correr antes migrations/10_movimiento_socio.sql en phpMyAdmin.');
}

$avisos = [];
$ids = demo_resolver_gimnasios($pdo, $dbConfig, $avisos);
foreach ($avisos as $av) {
    echo "[AVISO] $av\n";
}
$gyms = demo_gimnasios();
$nombreGym = function ($g) use ($gyms) {
    return $g === 0 ? 'Gimnasio actual' : $gyms[$g]['nombre'];
};

try {
    // ------------------------------------------------------------ 1. altas de los socios actuales
    $sinAlta = "
        FROM Usuario u
        WHERE u.es_admin = 'N' AND u.ID_Gimnasio IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM Movimiento_Socio m WHERE m.ID_Usuario = u.ID_Usuario AND m.tipo = 'alta')
    ";
    $n1 = (int) $pdo->query("SELECT COUNT(*) $sinAlta")->fetchColumn();
    echo "1. Altas de los socios que ya estaban: $n1\n";
    if ($aplicar && $n1 > 0) {
        $pdo->exec("
            INSERT INTO Movimiento_Socio (ID_Usuario, ID_Gimnasio, tipo, origen, fecha)
            SELECT u.ID_Usuario, u.ID_Gimnasio, 'alta', 'carga_inicial', CONCAT(COALESCE(u.fecha_alta, CURDATE()), ' 09:00:00')
            $sinAlta
        ");
    }

    // ------------------------------------------------------------ 2. bajas por 4 meses sin entrenar
    echo "\n2. Socios demo con " . DEMO_DIAS_BAJA . " dias o mas sin entrenar (se van el dia que cumplen los 4 meses):\n";
    $hace30 = demo_sumar_dias($hoy, -30);
    $hace60 = demo_sumar_dias($hoy, -60);
    $ctx = ['socios' => []]; // demo_dar_baja solo lo usa para avisarle al simulador en memoria
    foreach ($ids as $g => $idGym) {
        $filas = demo_inactivos($pdo, $idGym, $hoy);
        $tot = $u30 = $p30 = 0;
        if ($aplicar) {
            $pdo->beginTransaction();
        }
        foreach ($filas as $u) {
            $fechaBaja = demo_sumar_dias($u['ultima'], DEMO_DIAS_BAJA);
            if ($fechaBaja > $hoy) {
                $fechaBaja = $hoy; // no deberia pasar (el filtro ya exige 4 meses), por las dudas
            }
            if ($aplicar && !demo_dar_baja($pdo, $ctx, $u['ID_Usuario'], $u['DNI'], $idGym, "$fechaBaja 20:00:00")) {
                continue;
            }
            $tot++;
            if ($fechaBaja > $hace30) {
                $u30++;
            } elseif ($fechaBaja > $hace60) {
                $p30++;
            }
        }
        if ($aplicar) {
            $pdo->commit();
        }
        echo '   ' . str_pad($nombreGym($g), 20) . " bajas $tot (ultimos 30 dias: $u30 | 30 anteriores: $p30)\n";
    }

    // ------------------------------------------------------------ 3. socios extra que se fueron este mes
    echo "\n3. Socios demo extra que se fueron en los ultimos 30 dias:\n";
    $extra = DEMO_EXTRA_BAJAS;
    $ctx = demo_contexto($pdo, $ids);
    $sts = demo_sentencias($pdo);
    foreach ($gyms as $g => $cfg) {
        $cant = isset($extra[$cfg['clave']]) ? (int) $extra[$cfg['clave']] : 0;
        if ($cant <= 0) {
            continue;
        }
        if (!isset($ids[$g])) {
            echo '   ' . str_pad($nombreGym($g), 20) . " (el gimnasio no existe, se saltea)\n";
            continue;
        }
        $creados = 0;
        for ($i = 0; $i < $cant; $i++) {
            // Repartidos en el mes: se van hace 3, 8, 13, 18, 23... dias, despues de 5 a 8 meses de socios,
            // y entrenan hasta 2 a 4 dias antes de irse.
            $fechaBaja = demo_sumar_dias($hoy, -(3 + ($i * 5) % 26));
            $fechaAlta = demo_sumar_dias($fechaBaja, -(150 + ($i * 25) % 100));
            $ultimoDia = demo_sumar_dias($fechaBaja, -(2 + $i % 3));
            if ($fechaAlta < $cfg['apertura']) {
                $fechaAlta = demo_sumar_dias($cfg['apertura'], 1);
            }
            $dni = DEMO_DNI_SOCIO_MIN + $g * 1000000 + demo_dia_idx($fechaAlta) * 20 + DEMO_K_EXTRA;
            if (isset($ctx['socios'][$dni])) {
                continue; // ya se cargo en una corrida anterior
            }
            $creados++;
            if (!$aplicar) {
                echo "   - alta $fechaAlta, entrena hasta $ultimoDia, se va $fechaBaja\n";
                continue;
            }
            $pdo->beginTransaction();
            try {
                demo_crear_socio($pdo, $ctx, $sts, $g, $cfg, $fechaAlta, DEMO_K_EXTRA); // anota su alta
                $s = $ctx['socios'][$dni];
                $s['baja'] = demo_sumar_dias($ultimoDia, 1); // entrena hasta $ultimoDia inclusive
                for ($f = $fechaAlta; $f <= $ultimoDia; $f = demo_sumar_dias($f, 1)) {
                    demo_entrenar($sts, $ctx['rutina_ej'], $s, $f);
                }
                demo_dar_baja($pdo, $ctx, $s['id'], $dni, $ids[$g], "$fechaBaja 19:00:00");
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            echo "   - socio $dni: alta $fechaAlta, entrena hasta $ultimoDia, se va $fechaBaja\n";
        }
        echo '   ' . str_pad($nombreGym($g), 20) . " socios extra: $creados\n";
    }

    // ------------------------------------------------------------ resumen
    echo "\nRESUMEN (lo que veria el dashboard de cada gimnasio)\n";
    $st = $pdo->prepare("
        SELECT
          SUM(tipo = 'alta' AND fecha >= CURDATE() - INTERVAL 30 DAY) AS altas30,
          SUM(tipo = 'alta' AND fecha >= CURDATE() - INTERVAL 60 DAY AND fecha < CURDATE() - INTERVAL 30 DAY) AS altas60,
          SUM(tipo = 'baja' AND fecha >= CURDATE() - INTERVAL 30 DAY) AS bajas30,
          SUM(tipo = 'baja' AND fecha >= CURDATE() - INTERVAL 60 DAY AND fecha < CURDATE() - INTERVAL 30 DAY) AS bajas60
        FROM Movimiento_Socio WHERE ID_Gimnasio = ?
    ");
    $stSoc = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE ID_Gimnasio = ? AND es_admin = 'N'");
    foreach ($ids as $g => $idGym) {
        $st->execute([$idGym]);
        $r = $st->fetch();
        $stSoc->execute([$idGym]);
        echo '   ' . str_pad($nombreGym($g), 20) . ' socios ' . str_pad((string) $stSoc->fetchColumn(), 4)
           . ' | altas ult. 30 dias ' . (int) $r['altas30'] . ' (antes ' . (int) $r['altas60'] . ')'
           . ' | bajas ult. 30 dias ' . (int) $r['bajas30'] . ' (antes ' . (int) $r['bajas60'] . ")\n";
    }
    if (!$aplicar) {
        echo "\n(No se cambio nada. Los pasos 2 y 3 del resumen todavia no estan aplicados.)\n";
    }
    echo "FIN: " . date('Y-m-d H:i:s') . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cargar_movimientos: ' . $e->getMessage());
    demo_salir(500, '[ERROR] ' . $e->getMessage());
}
