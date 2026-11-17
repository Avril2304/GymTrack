<?php
// demo/limpiar_base.php - Deja la base limpia antes de la carga demo (uso unico).
//
//   ?token=...                 Solo muestra que se conserva y que se borraria (no toca nada).
//   ?token=...&confirmar=SI    Borra.   (token = db_config.php['demo_token'])
//
// SE CONSERVA: el gimnasio actual (demo_gimnasio_actual, o el del dueño admin@example.com),
//   su admin y las cuentas de db_config.php['limpiar_conservar'] (lista de mails), con sus
//   rutinas favoritas y personalizadas, y el catalogo: Clase, Ejercicio,
//   Ejercicio_ExerciseDB y las rutinas globales (Rutina.ID_Usuario IS NULL) con su Ejercitacion.
// SE BORRA: todos los demas usuarios y gimnasios, todo Historial, Asistencia y Solicitud
//   (tambien los de las cuentas conservadas, para que no se mezclen con los datos demo),
//   las rutinas personalizadas de los usuarios borrados, login_intentos y Automation_Log.
// DESPUES renumera los IDs de cada tabla para que queden seguidos (1, 2, 3...), actualiza todas
//   las columnas que los usan, deja el AUTO_INCREMENT en el ultimo + 1 y cierra las sesiones abiertas.
// Las tablas que no conoce (si hubiera) no se tocan: se listan como aviso, y en ese caso no se renumera.
//
// HACER ANTES UN RESPALDO DESDE phpMyAdmin. Despues de usarlo se puede borrar este archivo.

set_time_limit(300);
ignore_user_abort(true);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/../api/conexion.php';
require_once __DIR__ . '/demo_lib.php';

demo_parametros();
demo_verificar_token($dbConfig);
demo_bloquear($pdo);

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'SI';

echo "=== Limpieza de la base === " . date('Y-m-d H:i:s') . "\n\n";

// ---------------------------------------------------------------- que se conserva
$avisos = [];
$ids = demo_resolver_gimnasios($pdo, $dbConfig, $avisos);
if (!isset($ids[0])) {
    foreach ($avisos as $av) {
        echo "[AVISO] $av\n";
    }
    demo_salir(400, "No se encontro el gimnasio actual: no se borro nada.");
}
$st = $pdo->prepare("
    SELECT g.ID_Gimnasio, g.nombre, g.identificador, u.ID_Usuario, u.nombre AS anombre, u.apellido, u.mail
    FROM Gimnasio g JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno
    WHERE g.ID_Gimnasio = ?
");
$st->execute([$ids[0]]);
$keep = $st->fetch();
if (!$keep) {
    demo_salir(400, "El gimnasio actual no tiene admin dueño: no se borro nada.");
}
$gym = (int) $keep['ID_Gimnasio'];
$admin = (int) $keep['ID_Usuario'];

// Cuentas extra a conservar (por mail). Si alguna no existe, no se borra nada.
$conservar = [$admin];
$extra = isset($dbConfig['limpiar_conservar']) && is_array($dbConfig['limpiar_conservar']) ? $dbConfig['limpiar_conservar'] : [];
$stU = $pdo->prepare("SELECT ID_Usuario, nombre, apellido, mail, ID_Gimnasio FROM Usuario WHERE mail = ?");
$listaExtra = [];
foreach ($extra as $mail) {
    $mail = trim((string) $mail);
    $stU->execute([$mail]);
    $u = $stU->fetch();
    if (!$u) {
        demo_salir(400, "No existe ningun usuario con el mail '$mail' (limpiar_conservar): no se borro nada.");
    }
    if ((int) $u['ID_Usuario'] !== $admin) {
        $conservar[] = (int) $u['ID_Usuario'];
        $listaExtra[] = $u;
    }
}
$keepIn = implode(',', array_unique($conservar)); // solo enteros

echo "SE CONSERVA\n";
echo "  Gimnasio: {$keep['nombre']} ({$keep['identificador']}, ID $gym)\n";
echo "  Admin:    {$keep['anombre']} {$keep['apellido']} <{$keep['mail']}> (ID $admin)\n";
foreach ($listaExtra as $u) {
    $enGym = (int) $u['ID_Gimnasio'] === $gym ? ', socio del gimnasio' : '';
    echo "  Usuario:  {$u['nombre']} {$u['apellido']} <{$u['mail']}> (ID {$u['ID_Usuario']}$enGym)\n";
}
echo "  Catalogo: Clase, Ejercicio, Ejercicio_ExerciseDB, rutinas globales y su Ejercitacion\n\n";

// Rutinas personalizadas que se van: las de cualquier usuario que no se conserva.
$rutinasFuera = "SELECT ID_Rutina FROM Rutina WHERE ID_Usuario IS NOT NULL AND ID_Usuario NOT IN ($keepIn)";

// [descripcion, SQL de conteo, SQL de borrado] en orden de borrado (hijos antes que padres).
$pasos = [
    ['Movimiento_Socio', "SELECT COUNT(*) FROM Movimiento_Socio", "DELETE FROM Movimiento_Socio"],
    ['Historial', "SELECT COUNT(*) FROM Historial", "DELETE FROM Historial"],
    ['Asistencia', "SELECT COUNT(*) FROM Asistencia", "DELETE FROM Asistencia"],
    ['Solicitud', "SELECT COUNT(*) FROM Solicitud", "DELETE FROM Solicitud"],
    ['Usuario_RutinaDia',
        "SELECT COUNT(*) FROM Usuario_RutinaDia WHERE ID_Usuario NOT IN ($keepIn) OR ID_Rutina IN ($rutinasFuera)",
        "DELETE FROM Usuario_RutinaDia WHERE ID_Usuario NOT IN ($keepIn) OR ID_Rutina IN (SELECT ID_Rutina FROM ($rutinasFuera) t)"],
    ['Ejercitacion (de rutinas personalizadas)',
        "SELECT COUNT(*) FROM Ejercitacion WHERE ID_Rutina IN ($rutinasFuera)",
        "DELETE FROM Ejercitacion WHERE ID_Rutina IN (SELECT ID_Rutina FROM ($rutinasFuera) t)"],
    ['Rutina (personalizadas)',
        "SELECT COUNT(*) FROM Rutina WHERE ID_Usuario IS NOT NULL AND ID_Usuario NOT IN ($keepIn)",
        "DELETE FROM Rutina WHERE ID_Usuario IS NOT NULL AND ID_Usuario NOT IN ($keepIn)"],
    ['Gimnasio (otros)', "SELECT COUNT(*) FROM Gimnasio WHERE ID_Gimnasio <> $gym", "DELETE FROM Gimnasio WHERE ID_Gimnasio <> $gym"],
    ['Usuario (todos menos los conservados)', "SELECT COUNT(*) FROM Usuario WHERE ID_Usuario NOT IN ($keepIn)", "DELETE FROM Usuario WHERE ID_Usuario NOT IN ($keepIn)"],
    ['login_intentos', "SELECT COUNT(*) FROM login_intentos", "DELETE FROM login_intentos"],
    ['Automation_Log', "SELECT COUNT(*) FROM Automation_Log", "DELETE FROM Automation_Log"],
];

// Tablas que existen en la base (SHOW TABLES no necesita information_schema).
$tablas = [];
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM) as $t) {
    $tablas[strtolower($t[0])] = $t[0];
}
$conocidas = ['clase', 'ejercicio', 'ejercicio_exercisedb', 'ejercitacion', 'rutina', 'historial', 'asistencia',
              'solicitud', 'usuario_rutinadia', 'gimnasio', 'usuario', 'login_intentos', 'automation_log',
              'movimiento_socio'];

echo "SE BORRA\n";
$aplicar = [];
foreach ($pasos as $p) {
    $tabla = strtolower(strtok($p[0], ' '));
    if (!isset($tablas[$tabla])) {
        echo '  ' . str_pad($p[0], 42) . " (la tabla no existe, se saltea)\n";
        continue;
    }
    $n = (int) $pdo->query($p[1])->fetchColumn();
    echo '  ' . str_pad($p[0], 42) . " $n\n";
    $aplicar[] = $p;
}

$otras = array_diff(array_keys($tablas), $conocidas);
if ($otras) {
    echo "\n[AVISO] Tablas que este script no toca: " . implode(', ', array_map(function ($t) use ($tablas) {
        return $tablas[$t];
    }, $otras)) . "\n";
}

// ---------------------------------------------------------------- IDs seguidos
// Despues de borrar, cada tabla queda numerada 1, 2, 3... (en el orden que ya tenia) y su
// AUTO_INCREMENT sigue desde el ultimo, asi lo que cargue el simulador continua la serie.
// Tabla => [clave primaria, [[tabla que la referencia, columna], ...]]
$renumerar = [
    'Usuario'           => ['ID_Usuario', [['Historial', 'ID_Usuario'], ['Asistencia', 'ID_Usuario'],
                            ['Usuario_RutinaDia', 'ID_Usuario'], ['Solicitud', 'ID_Usuario'],
                            ['Rutina', 'ID_Usuario'], ['Gimnasio', 'ID_Usuario_Dueno'],
                            ['Movimiento_Socio', 'ID_Usuario']]],
    'Gimnasio'          => ['ID_Gimnasio', [['Usuario', 'ID_Gimnasio'], ['Asistencia', 'ID_Gimnasio'], ['Solicitud', 'ID_Gimnasio'],
                            ['Movimiento_Socio', 'ID_Gimnasio']]],
    'Clase'             => ['ID_Clase', [['Usuario', 'ID_Clase'], ['Rutina', 'ID_Clase']]],
    'Ejercicio'         => ['ID_Ejercicio', [['Ejercitacion', 'ID_Ejercicio'], ['Historial', 'ID_Ejercicio'],
                            ['Ejercicio_ExerciseDB', 'ID_Ejercicio']]],
    'Rutina'            => ['ID_Rutina', [['Ejercitacion', 'ID_Rutina'], ['Usuario_RutinaDia', 'ID_Rutina']]],
    'Ejercitacion'      => ['ID_Ejercitacion', []],
    'Usuario_RutinaDia' => ['ID_URD', []],
    'Historial'         => ['ID_Historial', []],
    'Asistencia'        => ['ID_Asistencia', []],
    'Solicitud'         => ['ID_Solicitud', []],
    'Movimiento_Socio'  => ['ID_Movimiento', []],
    'login_intentos'    => ['id', []],
];
// Con tablas desconocidas no se renumera: podrian guardar IDs que quedarian apuntando a otra fila.
$puedeRenumerar = !$otras;

echo "\nIDS SEGUIDOS\n";
if ($puedeRenumerar) {
    echo "  Despues de borrar, renumera desde 1 los IDs de: " . implode(', ', array_keys($renumerar)) . "\n";
    echo "  (y actualiza todas las columnas que los usan). Cierra todas las sesiones abiertas.\n";
} else {
    echo "  No se renumera porque hay tablas desconocidas (ver aviso). Solo se borra.\n";
}

if (!$confirmar) {
    echo "\nNo se borro nada. Si la lista es correcta y ya hiciste el respaldo, agregá &confirmar=SI a la URL.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();
    // las cuentas conservadas dejan de figurar en algun gimnasio que se borra
    $pdo->exec("UPDATE Usuario SET ID_Gimnasio = NULL WHERE ID_Usuario IN ($keepIn) AND ID_Gimnasio IS NOT NULL AND ID_Gimnasio <> $gym");
    foreach ($aplicar as $p) {
        $pdo->exec($p[2]);
    }
    $pdo->commit();
    echo "\nListo: base limpia.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('limpiar_base: ' . $e->getMessage());
    demo_salir(500, "[ERROR] No se borro nada: " . $e->getMessage());
}

if (!$puedeRenumerar) {
    echo "Siguiente paso: cron_actividad_demo.php?modo=estado\n";
    exit(0);
}

// Renumera $tabla.$pk (1..n en el orden actual) y todas sus referencias.
// En dos pasadas (+desplazamiento y despues -desplazamiento) para no chocar con claves unicas a mitad de camino.
function renumerar_tabla(PDO $pdo, array $tablas, $tabla, $pk, array $refs) {
    $off = 100000000;
    $t = $tablas[strtolower($tabla)];
    $max = (int) $pdo->query("SELECT COALESCE(MAX(`$pk`), 0) FROM `$t`")->fetchColumn();
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    if ($n === 0) {
        return "vacia";
    }
    if ($max === $n) {
        return "ya estaban seguidos (1 a $n)";
    }
    if ($max >= $off) {
        throw new RuntimeException("$tabla tiene IDs demasiado grandes para renumerar.");
    }
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS gt_mapa");
    $pdo->exec("CREATE TEMPORARY TABLE gt_mapa (viejo INT UNSIGNED NOT NULL PRIMARY KEY, nuevo INT UNSIGNED NOT NULL)");
    $pdo->exec("INSERT INTO gt_mapa (viejo, nuevo) SELECT `$pk`, ROW_NUMBER() OVER (ORDER BY `$pk`) FROM `$t`");
    foreach (array_merge([[$tabla, $pk]], $refs) as $r) {
        if (!isset($tablas[strtolower($r[0])])) {
            continue;
        }
        $rt = $tablas[strtolower($r[0])];
        $c = $r[1];
        $pdo->exec("UPDATE `$rt` x JOIN gt_mapa m ON x.`$c` = m.viejo SET x.`$c` = m.nuevo + $off WHERE m.viejo <> m.nuevo");
        $pdo->exec("UPDATE `$rt` SET `$c` = `$c` - $off WHERE `$c` >= $off");
    }
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS gt_mapa");
    return "renumerados 1 a $n (antes llegaban hasta $max)";
}

try {
    // Sin chequeo de claves foraneas: cada referencia se actualiza a mano, y asi tampoco corren
    // los ON UPDATE CASCADE (que la actualizarian dos veces).
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->beginTransaction();
    $resultado = [];
    foreach ($renumerar as $tabla => $def) {
        if (isset($tablas[strtolower($tabla)])) {
            $resultado[$tabla] = renumerar_tabla($pdo, $tablas, $tabla, $def[0], $def[1]);
        }
    }
    $pdo->commit();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    error_log('limpiar_base (renumerar): ' . $e->getMessage());
    demo_salir(500, "[ERROR] La base quedo limpia pero NO se renumero (no cambio ningun ID): " . $e->getMessage());
}

// Contadores: el proximo ID de cada tabla es el ultimo + 1 (ALTER TABLE confirma solo; va despues del commit).
foreach (array_merge(array_keys($renumerar), ['Automation_Log']) as $tabla) {
    if (!isset($tablas[strtolower($tabla)])) {
        continue;
    }
    $t = $tablas[strtolower($tabla)];
    $pk = isset($renumerar[$tabla]) ? $renumerar[$tabla][0] : null;
    $sig = 1;
    if ($pk !== null) {
        $sig = (int) $pdo->query("SELECT COALESCE(MAX(`$pk`), 0) + 1 FROM `$t`")->fetchColumn();
    }
    $pdo->exec("ALTER TABLE `$t` AUTO_INCREMENT = $sig");
    $estado = isset($resultado[$tabla]) ? $resultado[$tabla] . ', ' : '';
    echo '  ' . str_pad($tabla, 20) . " {$estado}proximo ID: $sig\n";
}

// Las sesiones guardan el ID del usuario: con los IDs cambiados hay que volver a iniciar sesion.
$borradas = 0;
$dirSesiones = dirname(__DIR__, 2) . '/sessions';
foreach (is_dir($dirSesiones) ? (glob($dirSesiones . '/sess_*') ?: []) : [] as $archivo) {
    if (@unlink($archivo)) {
        $borradas++;
    }
}
echo "\nSesiones cerradas: $borradas. Todos tienen que volver a iniciar sesion.\n";
echo "Siguiente paso: cron_actividad_demo.php?modo=estado\n";
