<?php
// demo/demo_lib.php - Simulador de actividad para la demo (presentacion del 30/11/2026).
// Solo debe ser incluido por cron_actividad_demo.php y reset_demo.php, nunca accedido directamente.
//
// COMO SE RECONOCEN LOS DATOS DEMO
//   Socios: mail @example.com y DNI de 8 cifras entre 90.000.000 y 95.999.999.
//           DNI = 90.000.000 + g * 1.000.000 + dia * 20 + k
//           (g = gimnasio 0..5, dia = dias desde el 01/03/2025, k = orden de alta en ese dia)
//   Admins: mail @example.com y DNI 99.000.000 + g (solo los 5 gimnasios nuevos).
//   Ningun usuario real puede quedar alcanzado por estas dos reglas a la vez.
//
// SIN ESTADO GUARDADO
//   Todo lo "al azar" sale de un hash de (DNI, fecha, ...). Por eso correr dos veces el mismo dia
//   genera exactamente los mismos socios y entrenamientos, y la base rechaza los repetidos
//   (DNI y mail unicos, Asistencia UNIQUE (ID_Usuario, fecha), Historial UNIQUE (usuario, ejercicio, fecha)).
//   El perfil, la fecha de abandono y los pesos de cada socio se recalculan desde su DNI.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../api/movimientos.php'; // altas y bajas de socios (Movimiento_Socio)

define('DEMO_INICIO', '2025-03-01');      // primer dia de la carga inicial
define('DEMO_FIN_CRON', '2026-12-14');    // ultimo dia que carga el cron; despues no hace nada
define('DEMO_DOMINIO', '@example.com');
define('DEMO_DNI_SOCIO_MIN', 90000000);
define('DEMO_DNI_SOCIO_MAX', 95999999);
define('DEMO_DNI_ADMIN', 99000000);       // + g

// BAJAS SIMULADAS (solo datos demo; el sistema real nunca da de baja a nadie por su cuenta)
// Un socio demo que lleva DEMO_DIAS_BAJA dias sin entrenar "decide irse": queda sin gimnasio y se
// anota su baja en Movimiento_Socio con origen 'simulador', como si hubiera tocado "Salir del gimnasio".
// Lo hace el cron diario (demo_simular_bajas) y, para el pasado, demo/cargar_movimientos.php.
define('DEMO_DIAS_BAJA', 120);            // 4 meses sin entrenar
// Ademas, cada mes una parte de los socios ACTIVOS decide irse (se muda, cambia de gimnasio...):
// cada dia, cada socio activo con al menos un mes de antiguedad se va con probabilidad
// DEMO_SALIDAS_MES / 30. Con 4,5% el gimnasio de examen pierde unos 7 socios por mes y los chicos, 1 a 3.
define('DEMO_SALIDAS_MES', 0.045);

// ---------------------------------------------------------------- configuracion de los gimnasios

// clases: ID_Clase => peso relativo (1 Universal, 2 Deportista, 3 Tonificacion, 4 Hipertrofia,
// 5 Adulto mayor, 6 Resistencia, 7 Powerlifting, 8 Funcional, 9 Combate, 10 Rehabilitacion).
// tam: cuantos socios nuevos por dia (ver demo_altas_del_dia). fundadores: socios del dia de apertura.
function demo_gimnasios() {
    return [
        0 => [
            'clave' => 'actual', 'apertura' => '2025-03-01', 'fundadores' => 6, 'tam' => 0.13,
            'clases' => [1 => 3, 3 => 3, 4 => 2, 8 => 2, 2 => 1, 6 => 1],
        ],
        1 => [
            'clave' => 'examen', 'apertura' => '2025-03-01', 'fundadores' => 15, 'tam' => 0.50,
            'nombre' => 'Gimnasio Examen', 'identificador' => 'gimnasio-examen',
            'direccion' => 'Av. Colón 1450', 'telefono' => '3514000001', 'mail' => 'contacto.examen@example.com',
            'admin' => ['mail' => 'examen@example.com', 'nombre' => 'Laura', 'apellido' => 'Benítez', 'sexo' => 'F'],
            'clases' => [1 => 3, 3 => 3, 4 => 3, 8 => 2, 2 => 2, 6 => 1, 5 => 1, 7 => 1, 10 => 1],
        ],
        2 => [
            'clave' => 'fuerza-norte', 'apertura' => '2025-06-15', 'fundadores' => 10, 'tam' => 0.27,
            'nombre' => 'Fuerza Norte', 'identificador' => 'fuerza-norte',
            'direccion' => 'Av. Rafael Núñez 4200', 'telefono' => '3514000002', 'mail' => 'contacto.fuerzanorte@example.com',
            'admin' => ['mail' => 'fuerzanorte@example.com', 'nombre' => 'Martín', 'apellido' => 'Herrera', 'sexo' => 'M'],
            'clases' => [4 => 5, 7 => 4, 1 => 2, 2 => 1],
        ],
        3 => [
            'clave' => 'movimiento-activo', 'apertura' => '2025-10-10', 'fundadores' => 8, 'tam' => 0.22,
            'nombre' => 'Movimiento Activo', 'identificador' => 'movimiento-activo',
            'direccion' => 'Bv. San Juan 820', 'telefono' => '3514000003', 'mail' => 'contacto.movactivo@example.com',
            'admin' => ['mail' => 'movimientoactivo@example.com', 'nombre' => 'Carolina', 'apellido' => 'Paz', 'sexo' => 'F'],
            'clases' => [8 => 4, 3 => 3, 6 => 3, 1 => 1],
        ],
        4 => [
            'clave' => 'combate-sur', 'apertura' => '2026-02-02', 'fundadores' => 6, 'tam' => 0.18,
            'nombre' => 'Club Combate Sur', 'identificador' => 'combate-sur',
            'direccion' => 'Av. Vélez Sarsfield 3100', 'telefono' => '3514000004', 'mail' => 'contacto.combatesur@example.com',
            'admin' => ['mail' => 'combatesur@example.com', 'nombre' => 'Diego', 'apellido' => 'Ferreyra', 'sexo' => 'M'],
            'clases' => [9 => 6, 2 => 3, 8 => 2],
        ],
        5 => [
            'clave' => 'equilibrio-rehab', 'apertura' => '2026-07-20', 'fundadores' => 5, 'tam' => 0.15,
            'nombre' => 'Equilibrio Rehab', 'identificador' => 'equilibrio-rehab',
            'direccion' => 'Obispo Trejo 560', 'telefono' => '3514000005', 'mail' => 'contacto.equilibrio@example.com',
            'admin' => ['mail' => 'equilibriorehab@example.com', 'nombre' => 'Sofía', 'apellido' => 'Molina', 'sexo' => 'F'],
            'clases' => [10 => 5, 5 => 4, 3 => 1],
        ],
    ];
}

// Multiplicador de altas por mes (enero = 0): picos en marzo y octubre, valle en invierno y diciembre.
function demo_temporada($mes1a12) {
    $t = [1.1, 1.3, 1.5, 1.1, 0.9, 0.7, 0.6, 0.9, 1.2, 1.4, 1.3, 0.7];
    return $t[$mes1a12 - 1];
}

// Perfiles de asistencia. p = probabilidad de ir segun el dia (0 = domingo ... 6 = sabado),
// manana = probabilidad de que prefiera el turno mañana.
function demo_perfiles() {
    return [
        'constante'   => ['w' => 0.20, 'manana' => 0.55, 'p' => [0.05, 0.85, 0.80, 0.85, 0.80, 0.75, 0.25]],
        'regular'     => ['w' => 0.35, 'manana' => 0.40, 'p' => [0.03, 0.65, 0.15, 0.65, 0.15, 0.60, 0.10]],
        'fin_semana'  => ['w' => 0.15, 'manana' => 0.65, 'p' => [0.45, 0.05, 0.05, 0.10, 0.15, 0.60, 0.75]],
        'esporadico'  => ['w' => 0.30, 'manana' => 0.35, 'p' => [0.05, 0.30, 0.22, 0.30, 0.22, 0.28, 0.10]],
    ];
}

// ---------------------------------------------------------------- azar reproducible

// Numero en [0, 1) que depende solo de $clave: misma clave, mismo numero, en cualquier servidor.
function demo_r($clave) {
    return hexdec(substr(md5($clave), 0, 7)) / 268435456;
}

function demo_entero($clave, $min, $max) {
    return $min + (int) floor(demo_r($clave) * ($max - $min + 1));
}

// Elige una clave de $pesos (clave => peso relativo).
function demo_elegir($clave, array $pesos) {
    $total = array_sum($pesos);
    $x = demo_r($clave) * $total;
    foreach ($pesos as $k => $p) {
        if ($x < $p) {
            return $k;
        }
        $x -= $p;
    }
    return array_key_last_compat($pesos);
}

function array_key_last_compat(array $a) {
    $keys = array_keys($a);
    return $keys[count($keys) - 1];
}

// ---------------------------------------------------------------- fechas

function demo_hoy() {
    return date('Y-m-d');
}

function demo_sumar_dias($fecha, $dias) {
    return gmdate('Y-m-d', strtotime($fecha . ' 00:00:00 UTC') + $dias * 86400);
}

// Dias desde el 01/03/2025 (sin efectos de husos ni horario de verano).
function demo_dia_idx($fecha) {
    return (int) round((strtotime($fecha . ' 00:00:00 UTC') - strtotime(DEMO_INICIO . ' 00:00:00 UTC')) / 86400);
}

function demo_fecha_valida($s) {
    if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return false;
    }
    list($y, $m, $d) = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}

// ---------------------------------------------------------------- acceso a los scripts

// Lee parametros tambien desde la linea de comandos: php script.php modo=diario token=...
function demo_parametros() {
    if (PHP_SAPI === 'cli' && !empty($GLOBALS['argv'])) {
        foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
            parse_str($arg, $p);
            $_GET = array_merge($_GET, $p);
        }
    }
}

function demo_salir($codigo, $msg) {
    if (PHP_SAPI !== 'cli') {
        http_response_code($codigo);
    }
    echo $msg . "\n";
    exit($codigo >= 400 ? 1 : 0);
}

// Corta si el token no coincide con db_config.php['demo_token'].
function demo_verificar_token(array $dbConfig) {
    $esperado = isset($dbConfig['demo_token']) ? (string) $dbConfig['demo_token'] : '';
    if (strlen($esperado) < 16) {
        demo_salir(403, "Deshabilitado: falta 'demo_token' (minimo 16 caracteres) en api/db_config.php.");
    }
    $recibido = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if (!hash_equals($esperado, $recibido)) {
        demo_salir(403, 'Acceso denegado.');
    }
}

// Evita dos corridas a la vez (por ejemplo, refrescar el navegador durante una tanda).
function demo_bloquear(PDO $pdo) {
    $ok = $pdo->query("SELECT GET_LOCK('gymtrack_demo', 0) AS l")->fetch();
    if (!$ok || (int) $ok['l'] !== 1) {
        demo_salir(409, 'Ya hay una corrida del simulador en curso. Esperá a que termine.');
    }
}

// ---------------------------------------------------------------- gimnasios demo

// Devuelve [g => ID_Gimnasio] de los gimnasios que existen. Avisos en $avisos.
function demo_resolver_gimnasios(PDO $pdo, array $dbConfig, array &$avisos) {
    $ids = [];
    $actual = isset($dbConfig['demo_gimnasio_actual']) ? trim((string) $dbConfig['demo_gimnasio_actual']) : '';
    if ($actual !== '') {
        $st = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE identificador = ?");
        $st->execute([$actual]);
    } else {
        $st = $pdo->prepare("
            SELECT g.ID_Gimnasio FROM Gimnasio g
            JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno
            WHERE u.mail = 'admin@example.com'
        ");
        $st->execute();
    }
    $row = $st->fetch();
    if ($row) {
        $ids[0] = (int) $row['ID_Gimnasio'];
    } else {
        $avisos[] = $actual !== ''
            ? "No existe un gimnasio con identificador '$actual' (demo_gimnasio_actual): se saltea el gimnasio actual."
            : "No hay gimnasio cuyo dueño tenga el mail admin@example.com: se saltea el gimnasio actual. "
              . "Definí 'demo_gimnasio_actual' en db_config.php.";
    }

    $st = $pdo->prepare("
        SELECT g.ID_Gimnasio, u.DNI FROM Gimnasio g
        LEFT JOIN Usuario u ON u.ID_Usuario = g.ID_Usuario_Dueno
        WHERE g.identificador = ?
    ");
    foreach (demo_gimnasios() as $g => $cfg) {
        if ($g === 0) {
            continue;
        }
        $st->execute([$cfg['identificador']]);
        $row = $st->fetch();
        if (!$row) {
            continue;
        }
        if ((string) $row['DNI'] !== (string) (DEMO_DNI_ADMIN + $g)) {
            $avisos[] = "El identificador '{$cfg['identificador']}' ya lo usa un gimnasio que no es demo: se saltea.";
            continue;
        }
        $ids[$g] = (int) $row['ID_Gimnasio'];
    }
    return $ids;
}

// Crea (si faltan) los 5 gimnasios demo y sus admins. Solo lo llama la carga inicial.
function demo_crear_gimnasios(PDO $pdo, array $dbConfig, array $ids, array &$log) {
    $clave = isset($dbConfig['demo_clave_admin']) ? (string) $dbConfig['demo_clave_admin'] : '';
    $selUsuario = $pdo->prepare("SELECT ID_Usuario, DNI, es_admin FROM Usuario WHERE mail = ? OR DNI = ?");
    $selGym = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE identificador = ? OR ID_Usuario_Dueno = ?");
    $insUsuario = $pdo->prepare("
        INSERT INTO Usuario (ID_Clase, nombre, apellido, peso, altura, DNI, fecha_nacimiento, fecha_alta,
                             sexo, mail, telefono, clave, foto, es_admin)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'default.jpg', 'S')
    ");
    $insGym = $pdo->prepare("
        INSERT INTO Gimnasio (nombre, identificador, direccion, telefono, mail, fecha_alta, activo, ID_Usuario_Dueno)
        VALUES (?, ?, ?, ?, ?, ?, 'S', ?)
    ");

    foreach (demo_gimnasios() as $g => $cfg) {
        if ($g === 0 || isset($ids[$g])) {
            continue;
        }
        $a = $cfg['admin'];
        $dni = (string) (DEMO_DNI_ADMIN + $g);

        $pdo->beginTransaction();
        try {
            $selUsuario->execute([$a['mail'], $dni]);
            $existentes = $selUsuario->fetchAll();
            $idAdmin = null;
            foreach ($existentes as $u) {
                if ((string) $u['DNI'] === $dni && $u['es_admin'] === 'S') {
                    $idAdmin = (int) $u['ID_Usuario'];
                } else {
                    throw new RuntimeException("El mail {$a['mail']} o el DNI $dni ya los usa otro usuario.");
                }
            }
            if ($idAdmin === null) {
                if (strlen($clave) < 8) {
                    throw new RuntimeException("Falta 'demo_clave_admin' (minimo 8 caracteres) en db_config.php para crear los admins demo.");
                }
                $esM = $a['sexo'] === 'M';
                $insUsuario->execute([
                    $a['nombre'], $a['apellido'], $esM ? 82.0 : 61.0, $esM ? 1.78 : 1.65, $dni,
                    $esM ? '1988-05-12' : '1991-09-23', $cfg['apertura'], $a['sexo'], $a['mail'],
                    $cfg['telefono'], password_hash($clave, PASSWORD_DEFAULT),
                ]);
                $idAdmin = (int) $pdo->lastInsertId();
                $log[] = "Admin creado: {$a['mail']} (ID $idAdmin)";
            }

            $selGym->execute([$cfg['identificador'], $idAdmin]);
            if ($selGym->fetch()) {
                throw new RuntimeException("El identificador '{$cfg['identificador']}' ya existe o el admin ya tiene gimnasio.");
            }
            $insGym->execute([
                $cfg['nombre'], $cfg['identificador'], $cfg['direccion'], $cfg['telefono'], $cfg['mail'],
                $cfg['apertura'], $idAdmin,
            ]);
            $ids[$g] = (int) $pdo->lastInsertId();
            $pdo->commit();
            $log[] = "Gimnasio creado: {$cfg['nombre']} ({$cfg['identificador']}, ID {$ids[$g]}), apertura {$cfg['apertura']}";
        } catch (Exception $e) {
            $pdo->rollBack();
            $log[] = "[ERROR] {$cfg['nombre']}: " . $e->getMessage();
        }
    }
    ksort($ids);
    return $ids;
}

// ---------------------------------------------------------------- contexto cargado de la base

// Carga lo que hace falta para simular: rutinas globales por clase, ejercicios de cada rutina
// y los socios demo que ya existen (con sus rutinas asignadas).
function demo_contexto(PDO $pdo, array $ids) {
    $ctx = [
        'gyms' => $ids,
        'rutinas_clase' => [],   // ID_Clase => [ID_Rutina, ...] (solo rutinas globales con ejercicios)
        'rutina_ej' => [],       // ID_Rutina => [[ID_Ejercicio, nombre], ...]
        'socios' => [],          // DNI => socio
        'clave_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // los socios demo no pueden entrar
    ];

    $rows = $pdo->query("
        SELECT r.ID_Rutina, r.ID_Clase, ej.ID_Ejercicio, e.nombre
        FROM Rutina r
        JOIN Ejercitacion ej ON ej.ID_Rutina = r.ID_Rutina
        JOIN Ejercicio e ON e.ID_Ejercicio = ej.ID_Ejercicio
        WHERE r.ID_Usuario IS NULL
        ORDER BY r.ID_Rutina, ej.ID_Ejercitacion
    ")->fetchAll();
    foreach ($rows as $r) {
        $rid = (int) $r['ID_Rutina'];
        if (!isset($ctx['rutina_ej'][$rid])) {
            $ctx['rutina_ej'][$rid] = [];
            $ctx['rutinas_clase'][(int) $r['ID_Clase']][] = $rid;
        }
        // una rutina puede repetir un ejercicio: se guarda una sola vez (Historial es unico por dia)
        $ctx['rutina_ej'][$rid][(int) $r['ID_Ejercicio']] = $r['nombre'];
    }
    if (!$ctx['rutina_ej']) {
        throw new RuntimeException('No hay rutinas globales con ejercicios (Rutina.ID_Usuario IS NULL).');
    }

    $st = $pdo->prepare("
        SELECT ID_Usuario, DNI, ID_Clase, sexo, fecha_alta, ID_Gimnasio
        FROM Usuario
        WHERE mail LIKE ? AND LENGTH(DNI) = 8 AND DNI BETWEEN ? AND ?
    ");
    $st->execute(['%' . DEMO_DOMINIO, (string) DEMO_DNI_SOCIO_MIN, (string) DEMO_DNI_SOCIO_MAX]);
    foreach ($st->fetchAll() as $u) {
        $ctx['socios'][(int) $u['DNI']] = demo_armar_socio($u, []);
    }

    $st = $pdo->prepare("
        SELECT urd.ID_Usuario, urd.ID_Rutina
        FROM Usuario_RutinaDia urd
        JOIN Usuario u ON u.ID_Usuario = urd.ID_Usuario
        WHERE u.mail LIKE ? AND LENGTH(u.DNI) = 8 AND u.DNI BETWEEN ? AND ?
    ");
    $st->execute(['%' . DEMO_DOMINIO, (string) DEMO_DNI_SOCIO_MIN, (string) DEMO_DNI_SOCIO_MAX]);
    $porId = [];
    foreach ($ctx['socios'] as $dni => $s) {
        $porId[$s['id']] = $dni;
    }
    foreach ($st->fetchAll() as $r) {
        $rid = (int) $r['ID_Rutina'];
        if (isset($porId[(int) $r['ID_Usuario']]) && isset($ctx['rutina_ej'][$rid])) {
            $ctx['socios'][$porId[(int) $r['ID_Usuario']]]['rutinas'][] = $rid;
        }
    }
    return $ctx;
}

// Socio en memoria: datos de la base + rasgos que salen de su DNI.
function demo_armar_socio(array $u, array $rutinas) {
    $dni = (int) $u['DNI'];
    $perfiles = demo_perfiles();
    $pesos = [];
    foreach ($perfiles as $k => $p) {
        $pesos[$k] = $p['w'];
    }
    $perfil = demo_elegir("perfil$dni", $pesos);
    $alta = $u['fecha_alta'];
    // 40% abandona entre la semana 3 y la 17 desde el alta
    $baja = demo_r("abandona$dni") < 0.4 ? demo_sumar_dias($alta, 21 + demo_entero("baja$dni", 0, 99)) : null;
    return [
        'id' => (int) $u['ID_Usuario'],
        'dni' => $dni,
        'g' => (int) floor(($dni - DEMO_DNI_SOCIO_MIN) / 1000000),
        'clase' => (int) $u['ID_Clase'],
        'sexo' => $u['sexo'],
        'alta' => $alta,
        'baja' => $baja,
        'perfil' => $perfil,
        'manana' => demo_r("turno$dni") < $perfiles[$perfil]['manana'],
        'id_gimnasio' => $u['ID_Gimnasio'] !== null ? (int) $u['ID_Gimnasio'] : null,
        'rutinas' => $rutinas,
    ];
}

// ---------------------------------------------------------------- generacion de socios

function demo_nombres() {
    return [
        'F' => ['Lucía', 'Sofía', 'Valentina', 'Camila', 'Martina', 'Julieta', 'Agustina', 'Florencia', 'Micaela',
                'Carla', 'Paula', 'Rocío', 'Daniela', 'Natalia', 'Luciana', 'Milagros', 'Abril', 'Antonella',
                'Brenda', 'Victoria', 'Romina', 'Ana', 'Belén', 'Cecilia', 'Gabriela', 'Lorena', 'Marina', 'Sabrina'],
        'M' => ['Juan', 'Mateo', 'Santiago', 'Tomás', 'Nicolás', 'Facundo', 'Lucas', 'Agustín', 'Joaquín',
                'Franco', 'Gonzalo', 'Ignacio', 'Matías', 'Federico', 'Lautaro', 'Bruno', 'Ezequiel', 'Emiliano',
                'Pablo', 'Diego', 'Martín', 'Sebastián', 'Leandro', 'Rodrigo', 'Maximiliano', 'Hernán', 'Gastón', 'Iván'],
        'apellidos' => ['González', 'Rodríguez', 'Gómez', 'Fernández', 'López', 'Díaz', 'Martínez', 'Pérez',
                'Romero', 'Sosa', 'Álvarez', 'Torres', 'Ruiz', 'Ramírez', 'Flores', 'Acosta', 'Benítez', 'Medina',
                'Suárez', 'Herrera', 'Aguirre', 'Pereyra', 'Gutiérrez', 'Giménez', 'Molina', 'Silva', 'Castro',
                'Rojas', 'Ortiz', 'Luna', 'Juárez', 'Cabrera', 'Ríos', 'Ferreyra', 'Godoy', 'Morales', 'Domínguez',
                'Quiroga', 'Vega', 'Ledesma', 'Bustos', 'Peralta', 'Vera', 'Ponce', 'Carrizo', 'Villalba'],
    ];
}

function demo_slug($s) {
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
                    'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n']);
    return preg_replace('/[^a-z]/', '', strtolower($s));
}

// Cuantos socios nuevos entran ese dia en el gimnasio g (0 a 3; la mayoria de los dias, ninguno).
function demo_altas_del_dia($g, array $cfg, $fecha) {
    if ($fecha === $cfg['apertura']) {
        return $cfg['fundadores'];
    }
    $m = demo_temporada((int) substr($fecha, 5, 2)) * $cfg['tam'];
    $r = demo_r("altas$g$fecha");
    if ($r < 0.05 * $m) return 3;
    if ($r < 0.17 * $m) return 2;
    if ($r < 0.45 * $m) return 1;
    return 0;
}

// Inserta el socio (si no existe) con sus rutinas. Devuelve true si lo creo ahora.
function demo_crear_socio(PDO $pdo, array &$ctx, array $sts, $g, array $cfg, $fecha, $k) {
    $dni = DEMO_DNI_SOCIO_MIN + $g * 1000000 + demo_dia_idx($fecha) * 20 + $k;
    if (isset($ctx['socios'][$dni])) {
        return false;
    }
    $n = demo_nombres();
    $sexo = demo_r("sexo$dni") < 0.5 ? 'F' : 'M';
    $clase = (int) demo_elegir("clase$dni", $cfg['clases']);
    if (empty($ctx['rutinas_clase'][$clase])) {
        $clase = isset($ctx['rutinas_clase'][1]) ? 1 : (int) array_keys($ctx['rutinas_clase'])[0];
    }
    $nombre = $n[$sexo][demo_entero("nom$dni", 0, count($n[$sexo]) - 1)];
    $apellido = $n['apellidos'][demo_entero("ape$dni", 0, count($n['apellidos']) - 1)];
    $local = substr(demo_slug($nombre) . '.' . demo_slug($apellido), 0, 21) . '.' . base_convert((string) ($dni - DEMO_DNI_SOCIO_MIN), 10, 36);
    $mail = $local . DEMO_DOMINIO; // <= 40 caracteres (Usuario.mail es varchar(40))

    if ($clase === 5) {
        $edad = demo_entero("edad$dni", 60, 78);
    } elseif ($clase === 10) {
        $edad = demo_entero("edad$dni", 25, 66);
    } else {
        $edad = demo_entero("edad$dni", 17, 30) + (demo_r("edad2$dni") < 0.4 ? demo_entero("edad3$dni", 0, 22) : 0);
    }
    $nacimiento = demo_sumar_dias($fecha, -($edad * 365 + demo_entero("nac$dni", 0, 364)));
    $peso = $sexo === 'M' ? demo_entero("peso$dni", 620, 980) / 10 : demo_entero("peso$dni", 500, 800) / 10;
    $altura = $sexo === 'M' ? demo_entero("alt$dni", 1650, 1920) / 1000 : demo_entero("alt$dni", 1520, 1780) / 1000;
    $telefono = '351' . str_pad((string) demo_entero("tel$dni", 0, 9999999), 7, '0', STR_PAD_LEFT);

    $sts['insSocio']->execute([
        $clase, $nombre, $apellido, $peso, $altura, (string) $dni, $nacimiento, $fecha, $sexo, $mail,
        $telefono, $ctx['clave_hash'], $ctx['gyms'][$g],
    ]);
    $id = (int) $pdo->lastInsertId();

    // 1 o 2 rutinas globales de su clase
    $disp = $ctx['rutinas_clase'][$clase];
    usort($disp, function ($a, $b) use ($dni) {
        return demo_r("rut$dni-$a") <=> demo_r("rut$dni-$b");
    });
    $rutinas = array_slice($disp, 0, demo_r("nrut$dni") < 0.6 ? 2 : 1);
    foreach ($rutinas as $rid) {
        $sts['insRutina']->execute([$id, $rid]);
    }

    // Alta en el historial de movimientos (misma transaccion que el resto del dia)
    gt_registrar_movimiento($pdo, $id, $ctx['gyms'][$g], 'alta', 'simulador', "$fecha 09:00:00");

    $ctx['socios'][$dni] = demo_armar_socio([
        'ID_Usuario' => $id, 'DNI' => $dni, 'ID_Clase' => $clase, 'sexo' => $sexo,
        'fecha_alta' => $fecha, 'ID_Gimnasio' => $ctx['gyms'][$g],
    ], $rutinas);
    return true;
}

// ---------------------------------------------------------------- entrenamientos

function demo_es_peso_corporal($nombre) {
    return (bool) preg_match('/plancha|crunch|abdominal|dominada|fondos|mountain|ab wheel|banda|russian|glute bridge/i', $nombre);
}

// Peso de un ejercicio para un socio en una fecha: base segun ejercicio y socio, sube con las semanas.
function demo_peso($s, $idEj, $nombre, $fecha) {
    if (demo_es_peso_corporal($nombre)) {
        return 0;
    }
    $f = $s['sexo'] === 'M';
    if (preg_match('/press de pierna/i', $nombre)) {
        $rango = $f ? [60, 160] : [30, 90];
    } elseif (preg_match('/sentadilla|peso muerto|hip thrust|buenos d/i', $nombre)) {
        $rango = $f ? [40, 100] : [20, 60];
    } elseif (preg_match('/press de banca|remo con barra|press militar|press inclinado/i', $nombre)) {
        $rango = $f ? [25, 70] : [10, 35];
    } elseif (preg_match('/jal[oó]n|remo|press de hombros|press arnold|peck|apertura|polea|femoral|cu[aá]driceps|abducci|aducci/i', $nombre)) {
        $rango = $f ? [20, 55] : [10, 32];
    } else {
        $rango = $f ? [6, 22] : [3, 12];
    }
    $mult = [5 => 0.45, 10 => 0.5, 3 => 0.8, 7 => 1.3, 4 => 1.1];
    $base = demo_entero("base{$s['dni']}-$idEj", $rango[0], $rango[1]) * (isset($mult[$s['clase']]) ? $mult[$s['clase']] : 1);
    $semanas = max(0, demo_dia_idx($fecha) - demo_dia_idx($s['alta'])) / 7;
    $peso = $base * (1 + 0.3 * (1 - exp(-$semanas / 20)));
    $paso = $peso >= 20 ? 2.5 : 1;
    return max($paso, round($peso / $paso) * $paso);
}

function demo_reps($s, $nombre, $clave) {
    if (demo_es_peso_corporal($nombre)) {
        return demo_entero($clave, 10, 20);
    }
    switch ($s['clase']) {
        case 7: return demo_entero($clave, 3, 6);
        case 4: return demo_entero($clave, 8, 12);
        case 6:
        case 8: return demo_entero($clave, 12, 20);
        case 5:
        case 10: return demo_entero($clave, 10, 15);
        default: return demo_entero($clave, 8, 15);
    }
}

// Decide si el socio entrena ese dia y, si entrena, guarda la asistencia y sus ejercicios.
// Devuelve [asistencias nuevas, filas de historial nuevas].
function demo_entrenar(array $sts, array $rutinaEj, array $s, $fecha) {
    if ($s['id_gimnasio'] === null || empty($s['rutinas']) || $fecha < $s['alta']) {
        return [0, 0];
    }
    if ($s['baja'] !== null && $fecha >= $s['baja']) {
        return [0, 0];
    }
    $dni = $s['dni'];
    $perfiles = demo_perfiles();
    $dow = (int) gmdate('w', strtotime($fecha . ' 00:00:00 UTC'));
    $prob = $fecha === $s['alta'] ? 0.75 : $perfiles[$s['perfil']]['p'][$dow];
    if (demo_r("va$dni$fecha") >= $prob) {
        return [0, 0];
    }

    // turno: el preferido el 85% de las veces
    $manana = demo_r("tm$dni$fecha") < 0.85 ? $s['manana'] : !$s['manana'];
    $minuto = $manana ? demo_entero("min$dni$fecha", 7 * 60, 13 * 60 + 30) : demo_entero("min$dni$fecha", 14 * 60 + 30, 21 * 60 + 30);
    $seg = demo_entero("seg$dni$fecha", 0, 59);
    $hora = sprintf('%02d:%02d:%02d', intdiv($minuto, 60), $minuto % 60, $seg);
    $turno = $hora < '14:00:00' ? 'M' : 'T'; // misma regla que guardar_entrenamiento.php

    // Si ese dia ya esta cargado no se intenta insertar: un INSERT IGNORE descartado igual
    // consume un numero de AUTO_INCREMENT y dejaria huecos en los IDs.
    $sts['existeAsistencia']->execute([$s['id'], $fecha]);
    $ya = $sts['existeAsistencia']->fetchColumn();
    $sts['existeAsistencia']->closeCursor();
    if ($ya) {
        return [0, 0];
    }

    $sts['insAsistencia']->execute([$s['id'], $s['id_gimnasio'], $fecha, $hora, $turno]);
    $nA = $sts['insAsistencia']->rowCount();

    // rutina del dia y 4 a 6 de sus ejercicios, en el orden de la rutina
    $rid = $s['rutinas'][demo_entero("rd$dni$fecha", 0, count($s['rutinas']) - 1)];
    $ejs = $rutinaEj[$rid];
    $orden = array_keys($ejs);
    $sorteo = $orden;
    usort($sorteo, function ($a, $b) use ($dni, $fecha) {
        return demo_r("ej$dni$fecha-$a") <=> demo_r("ej$dni$fecha-$b");
    });
    $elegidos = array_flip(array_slice($sorteo, 0, demo_entero("nej$dni$fecha", 4, 6)));

    $nH = 0;
    $i = 0;
    foreach ($orden as $idEj) {
        if (!isset($elegidos[$idEj])) {
            continue;
        }
        $nombre = $ejs[$idEj];
        $horaEj = gmdate('H:i:s', strtotime("1970-01-01 $hora UTC") + $i * 9 * 60);
        $sts['insHistorial']->execute([
            $s['id'], $idEj, $fecha, $horaEj,
            demo_peso($s, $idEj, $nombre, $fecha),
            demo_reps($s, $nombre, "rep$dni$fecha-$idEj"),
            demo_r("ser$dni$fecha-$idEj") < 0.6 ? 3 : 4,
        ]);
        $nH += $sts['insHistorial']->rowCount();
        $i++;
    }
    return [$nA, $nH];
}

// ---------------------------------------------------------------- un dia completo

function demo_sentencias(PDO $pdo) {
    return [
        'insSocio' => $pdo->prepare("
            INSERT INTO Usuario (ID_Clase, nombre, apellido, peso, altura, DNI, fecha_nacimiento, fecha_alta,
                                 sexo, mail, telefono, clave, foto, es_admin, ID_Gimnasio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'default.jpg', 'N', ?)
        "),
        'insRutina' => $pdo->prepare("INSERT INTO Usuario_RutinaDia (ID_Usuario, ID_Rutina) VALUES (?, ?)"),
        'existeAsistencia' => $pdo->prepare("SELECT 1 FROM Asistencia WHERE ID_Usuario = ? AND fecha = ? LIMIT 1"),
        'insAsistencia' => $pdo->prepare("
            INSERT IGNORE INTO Asistencia (ID_Usuario, ID_Gimnasio, fecha, hora_entrada, turno)
            VALUES (?, ?, ?, ?, ?)
        "),
        'insHistorial' => $pdo->prepare("
            INSERT IGNORE INTO Historial (ID_Usuario, ID_Ejercicio, fecha, hora, peso, repeticiones, series)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        "),
    ];
}

// ---------------------------------------------------------------- bajas simuladas

// Socios demo del gimnasio $idGimnasio que al dia $fecha llevan DEMO_DIAS_BAJA dias o mas sin entrenar
// (o sin ningun entrenamiento desde su alta). Devuelve filas [ID_Usuario, DNI, ultima].
// "ultima" es el ultimo entrenamiento hasta $fecha, o la fecha de alta si nunca entreno.
function demo_inactivos(PDO $pdo, $idGimnasio, $fecha) {
    $st = $pdo->prepare("
        SELECT u.ID_Usuario, u.DNI, COALESCE(MAX(a.fecha), u.fecha_alta) AS ultima
        FROM Usuario u
        LEFT JOIN Asistencia a ON a.ID_Usuario = u.ID_Usuario AND a.fecha <= ?
        WHERE u.ID_Gimnasio = ? AND u.es_admin = 'N'
          AND u.mail LIKE ? AND LENGTH(u.DNI) = 8 AND u.DNI BETWEEN ? AND ?
        GROUP BY u.ID_Usuario, u.DNI, u.fecha_alta
        HAVING ultima <= ?
    ");
    $st->execute([
        $fecha, (int) $idGimnasio, '%' . DEMO_DOMINIO, (string) DEMO_DNI_SOCIO_MIN, (string) DEMO_DNI_SOCIO_MAX,
        demo_sumar_dias($fecha, -DEMO_DIAS_BAJA),
    ]);
    return $st->fetchAll();
}

// Da de baja a un socio demo: queda sin gimnasio y se anota la baja (origen 'simulador').
// Devuelve true si lo dio de baja ahora (false si ya no estaba en ese gimnasio).
function demo_dar_baja(PDO $pdo, array &$ctx, $idUsuario, $dni, $idGimnasio, $fechaHora) {
    $st = $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = NULL WHERE ID_Usuario = ? AND ID_Gimnasio = ?");
    $st->execute([(int) $idUsuario, (int) $idGimnasio]);
    if ($st->rowCount() === 0) {
        return false;
    }
    gt_registrar_movimiento($pdo, $idUsuario, $idGimnasio, 'baja', 'simulador', $fechaHora);
    if (isset($ctx['socios'][(int) $dni])) {
        $ctx['socios'][(int) $dni]['id_gimnasio'] = null; // el simulador deja de cargarle entrenamientos
    }
    return true;
}

// Cron diario: los socios demo del gimnasio g que hoy cumplen 4 meses sin venir deciden irse.
function demo_simular_bajas(PDO $pdo, array &$ctx, $g, $fecha) {
    $n = 0;
    foreach (demo_inactivos($pdo, $ctx['gyms'][$g], $fecha) as $u) {
        if (demo_dar_baja($pdo, $ctx, $u['ID_Usuario'], $u['DNI'], $ctx['gyms'][$g], "$fecha 20:00:00")) {
            $n++;
        }
    }
    return $n;
}

// Cron diario: algunos socios demo ACTIVOS del gimnasio g deciden irse hoy (ver DEMO_SALIDAS_MES).
// Activo = tiene gimnasio, entra hace 30 dias o mas y no abandono (todavia entrena).
// Sale de un hash de (DNI, fecha): correrlo dos veces el mismo dia da lo mismo.
function demo_simular_salidas(PDO $pdo, array &$ctx, $g, $fecha) {
    $n = 0;
    $prob = DEMO_SALIDAS_MES / 30;
    $antiguedad = demo_sumar_dias($fecha, -30);
    foreach ($ctx['socios'] as $dni => $s) {
        if ($s['g'] !== $g || $s['id_gimnasio'] === null || $s['alta'] > $antiguedad) {
            continue;
        }
        if ($s['baja'] !== null && $fecha >= $s['baja']) {
            continue; // ya dejo de venir: si no vuelve, se va por la regla de los 4 meses
        }
        if (demo_r("sale$dni$fecha") >= $prob) {
            continue;
        }
        if (demo_dar_baja($pdo, $ctx, $s['id'], $dni, $s['id_gimnasio'], "$fecha 18:00:00")) {
            $n++;
        }
    }
    return $n;
}

// Simula un dia en todos los gimnasios demo. Todo el dia va en una transaccion.
// $simularBajas: solo el cron diario (en la carga inicial el pasado lo arma cargar_movimientos.php).
// Devuelve [g => ['altas' => n, 'bajas' => n, 'asistencias' => n, 'historial' => n]].
function demo_procesar_dia(PDO $pdo, array &$ctx, array $sts, $fecha, $simularBajas = false) {
    $res = [];
    $pdo->beginTransaction();
    try {
        foreach (demo_gimnasios() as $g => $cfg) {
            if (!isset($ctx['gyms'][$g]) || $fecha < $cfg['apertura']) {
                continue;
            }
            $r = ['altas' => 0, 'bajas' => 0, 'asistencias' => 0, 'historial' => 0];
            $n = demo_altas_del_dia($g, $cfg, $fecha);
            for ($k = 0; $k < $n; $k++) {
                if (demo_crear_socio($pdo, $ctx, $sts, $g, $cfg, $fecha, $k)) {
                    $r['altas']++;
                }
            }
            if ($simularBajas) {
                // los que cumplen 4 meses sin venir + algunos activos que deciden irse
                $r['bajas'] = demo_simular_bajas($pdo, $ctx, $g, $fecha)
                            + demo_simular_salidas($pdo, $ctx, $g, $fecha);
            }
            foreach ($ctx['socios'] as $s) {
                if ($s['g'] !== $g) {
                    continue;
                }
                list($a, $h) = demo_entrenar($sts, $ctx['rutina_ej'], $s, $fecha);
                $r['asistencias'] += $a;
                $r['historial'] += $h;
            }
            $res[$g] = $r;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $res;
}
