<?php
// api/login.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'sesion.php';
gt_iniciar_sesion();
require_once 'conexion.php';
$in = json_decode(file_get_contents('php://input'), true);

$email = trim($in['email'] ?? ($in['mail'] ?? ''));
$password = $in['password'] ?? ($in['clave'] ?? '');

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'Email y contraseña requeridos']);
    exit;
}

// ---------------------------------------------------------------------------
// Rate Limiting (por mail e IP: máximo 5 intentos fallidos en 15 minutos)
// ---------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limiteSuperado = false;

try {
    // Limpieza de intentos vencidos (> 15 minutos)
    $pdo->prepare("DELETE FROM login_intentos WHERE fecha_intento < NOW() - INTERVAL 15 MINUTE")->execute();

    // Contar intentos fallidos recientes para este correo o esta IP
    $stmtRate = $pdo->prepare("SELECT COUNT(*) FROM login_intentos WHERE (mail = ? OR ip = ?) AND fecha_intento >= NOW() - INTERVAL 15 MINUTE");
    $stmtRate->execute([$email, $ip]);
    $intentosFallidos = (int) $stmtRate->fetchColumn();

    if ($intentosFallidos >= 5) {
        $limiteSuperado = true;
    }
} catch (Throwable $eRate) {
    // Fallback resiliente en caso de que la tabla login_intentos aún no se haya migrado en la base
    $kSession = 'throttle_login_' . md5($ip . '_' . $email);
    $ahora = time();
    $historial = [];
    foreach (($_SESSION[$kSession] ?? []) as $t) {
        if ($t > $ahora - 900) {
            $historial[] = $t;
        }
    }
    if (count($historial) >= 5) {
        $limiteSuperado = true;
    }
}

if ($limiteSuperado) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'msg' => 'Demasiados intentos fallidos. Por favor, esperá 15 minutos antes de volver a intentar.'
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Búsqueda y Verificación de Credenciales (Anti-enumeración y mitigación de Timing Attacks)
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE mail = :m LIMIT 1");
$stmt->execute(['m' => $email]);
$u = $stmt->fetch();

$esValido = false;
// Hash dummy para asegurar tiempo constante de CPU aunque el usuario no exista
$dummyHash = '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUV0123456789';

if ($u) {
    if (password_verify($password, $u['clave'])) {
        $esValido = true;
    } elseif ($password === $u['clave']) {
        // Migración de contraseñas viejas en texto plano
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE Usuario SET clave = ? WHERE ID_Usuario = ?")->execute([$newHash, $u['ID_Usuario']]);
        $esValido = true;
    } elseif (hash('sha256', $password) === $u['clave']) {
        // Migración de hashes SHA-256 legacy
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE Usuario SET clave = ? WHERE ID_Usuario = ?")->execute([$newHash, $u['ID_Usuario']]);
        $esValido = true;
    }
} else {
    // Corremos verificación contra hash dummy para evitar timing attacks
    password_verify($password, $dummyHash);
}

if (!$esValido) {
    // Registrar intento fallido
    try {
        $pdo->prepare("INSERT INTO login_intentos (mail, ip) VALUES (?, ?)")->execute([$email, $ip]);
    } catch (Throwable $eLog) {
        // Fallback a sesión
        $kSession = 'throttle_login_' . md5($ip . '_' . $email);
        $historial = $_SESSION[$kSession] ?? [];
        $historial[] = time();
        $_SESSION[$kSession] = $historial;
    }

    echo json_encode(['ok' => false, 'msg' => 'Email o contraseña incorrectos']);
    exit;
}

// ---------------------------------------------------------------------------
// Login Exitoso: limpiar historial de intentos y regenerar sesión
// ---------------------------------------------------------------------------
try {
    $pdo->prepare("DELETE FROM login_intentos WHERE mail = ? OR ip = ?")->execute([$email, $ip]);
} catch (Throwable $eClear) {
    $kSession = 'throttle_login_' . md5($ip . '_' . $email);
    unset($_SESSION[$kSession]);
}

if (($u['email_verificado'] ?? 'S') === 'N') {
    http_response_code(403);
    echo json_encode([
        'ok'     => false,
        'codigo' => 'EMAIL_NO_VERIFICADO',
        'msg'    => 'Tu correo todavía no fue verificado. Revisá tu casilla (o spam) para confirmar tu cuenta.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Nueva sesión al autenticar (evita fijación de sesión).
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $u['ID_Usuario'];
$_SESSION['es_admin'] = $u['es_admin'] ?? 'N';

// Para un admin, "su" gimnasio es el que posee.
$idGimnasioUi = isset($u['ID_Gimnasio']) && $u['ID_Gimnasio'] !== null ? (int) $u['ID_Gimnasio'] : null;
if (($u['es_admin'] ?? 'N') === 'S') {
    $stmtDueno = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE ID_Usuario_Dueno = ? AND activo = 'S' LIMIT 1");
    $stmtDueno->execute([(int) $u['ID_Usuario']]);
    $gDueno = $stmtDueno->fetch();
    if ($gDueno) {
        $idGimnasioUi = (int) $gDueno['ID_Gimnasio'];
    }
}
$_SESSION['id_gimnasio'] = $idGimnasioUi;

echo json_encode([
    'ok' => true,
    'msg' => 'Bienvenido ' . $u['nombre'],
    'user' => [
        'ID_Usuario'  => (int) $u['ID_Usuario'],
        'nombre'      => $u['nombre'],
        'apellido'    => $u['apellido'],
        'email'       => $u['mail'],
        'foto'        => $u['foto'],
        'DNI'         => $u['DNI'],
        'telefono'    => $u['telefono'],
        'sexo'        => $u['sexo'],
        'es_admin'    => $u['es_admin'] ?? 'N',
        'ID_Gimnasio' => $idGimnasioUi
    ]
]);
