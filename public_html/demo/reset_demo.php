<?php
// demo/reset_demo.php - Borra TODOS los datos que cargo cron_actividad_demo.php.
//
//   ?token=...                 Solo muestra que se borraria (no toca nada).
//   ?token=...&confirmar=SI    Borra.
//
// Que borra: socios demo (mail @example.com y DNI 90.000.000-95.999.999) con su historial,
// asistencias, rutinas asignadas y solicitudes; los 5 gimnasios demo y sus admins
// (mail @example.com y DNI 99.000.000-99.000.005).
// Que NO borra: el gimnasio actual, su admin y sus socios reales, ni ningun otro usuario.

set_time_limit(300);
ignore_user_abort(true);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../api/conexion.php';
require_once __DIR__ . '/demo_lib.php';

demo_parametros();
demo_verificar_token($dbConfig);
demo_bloquear($pdo);

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'SI';

// Condiciones (u = Usuario). Mismas marcas que demo_lib.php.
$socio = "u.mail LIKE :dom AND LENGTH(u.DNI) = 8 AND u.DNI BETWEEN :smin AND :smax";
$admin = "u.mail LIKE :dom AND LENGTH(u.DNI) = 8 AND u.DNI BETWEEN :amin AND :amax AND u.es_admin = 'S'";
$bindSocio = ['dom' => '%' . DEMO_DOMINIO, 'smin' => (string) DEMO_DNI_SOCIO_MIN, 'smax' => (string) DEMO_DNI_SOCIO_MAX];
$bindAdmin = ['dom' => '%' . DEMO_DOMINIO, 'amin' => (string) DEMO_DNI_ADMIN, 'amax' => (string) (DEMO_DNI_ADMIN + 99)];

$identificadores = [];
foreach (demo_gimnasios() as $g => $cfg) {
    if ($g > 0) {
        $identificadores[] = $pdo->quote($cfg['identificador']);
    }
}
$listaIdent = implode(',', $identificadores);

function contar(PDO $pdo, $sql, array $bind) {
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    return (int) $st->fetchColumn();
}

$conteo = [
    'Historial'         => contar($pdo, "SELECT COUNT(*) FROM Historial h JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario WHERE $socio", $bindSocio),
    'Asistencia'        => contar($pdo, "SELECT COUNT(*) FROM Asistencia a JOIN Usuario u ON u.ID_Usuario = a.ID_Usuario WHERE $socio", $bindSocio),
    'Usuario_RutinaDia' => contar($pdo, "SELECT COUNT(*) FROM Usuario_RutinaDia r JOIN Usuario u ON u.ID_Usuario = r.ID_Usuario WHERE $socio", $bindSocio),
    'Socios demo'       => contar($pdo, "SELECT COUNT(*) FROM Usuario u WHERE $socio", $bindSocio),
    'Gimnasios demo'    => contar($pdo, "SELECT COUNT(*) FROM Gimnasio g JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno WHERE g.identificador IN ($listaIdent) AND $admin", $bindAdmin),
    'Admins demo'       => contar($pdo, "SELECT COUNT(*) FROM Usuario u WHERE $admin", $bindAdmin),
];

echo "=== Reset de datos demo === " . date('Y-m-d H:i:s') . "\n\n";
foreach ($conteo as $tabla => $n) {
    echo str_pad($tabla, 20) . " $n\n";
}

if (!$confirmar) {
    echo "\nNo se borro nada. Para borrar, agregá &confirmar=SI a la URL.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();
    $pasos = [
        ["DELETE h FROM Historial h JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario WHERE $socio", $bindSocio],
        ["DELETE a FROM Asistencia a JOIN Usuario u ON u.ID_Usuario = a.ID_Usuario WHERE $socio", $bindSocio],
        ["DELETE r FROM Usuario_RutinaDia r JOIN Usuario u ON u.ID_Usuario = r.ID_Usuario WHERE $socio", $bindSocio],
        ["DELETE s FROM Solicitud s JOIN Usuario u ON u.ID_Usuario = s.ID_Usuario WHERE $socio", $bindSocio],
        ["DELETE u FROM Usuario u WHERE $socio", $bindSocio],
        // los gimnasios demo pueden tener socios reales que se sumaron despues: quedan sin gimnasio (FK SET NULL)
        ["DELETE a FROM Asistencia a JOIN Gimnasio g ON g.ID_Gimnasio = a.ID_Gimnasio JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno WHERE g.identificador IN ($listaIdent) AND $admin", $bindAdmin],
        ["DELETE s FROM Solicitud s JOIN Gimnasio g ON g.ID_Gimnasio = s.ID_Gimnasio JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno WHERE g.identificador IN ($listaIdent) AND $admin", $bindAdmin],
        ["UPDATE Usuario x JOIN Gimnasio g ON g.ID_Gimnasio = x.ID_Gimnasio JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno SET x.ID_Gimnasio = NULL WHERE g.identificador IN ($listaIdent) AND $admin", $bindAdmin],
        ["DELETE g FROM Gimnasio g JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno WHERE g.identificador IN ($listaIdent) AND $admin", $bindAdmin],
        ["DELETE u FROM Usuario u WHERE $admin AND NOT EXISTS (SELECT 1 FROM Gimnasio g WHERE g.ID_Usuario_Dueno = u.ID_Usuario)", $bindAdmin],
    ];
    foreach ($pasos as $p) {
        $st = $pdo->prepare($p[0]);
        $st->execute($p[1]);
    }
    $pdo->commit();
    echo "\nListo: datos demo borrados.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('reset_demo: ' . $e->getMessage());
    demo_salir(500, "[ERROR] No se borro nada: " . $e->getMessage());
}
