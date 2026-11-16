<?php
// api/login.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'sesion.php';
gt_iniciar_sesion();
require_once 'conexion.php';
$in = json_decode(file_get_contents('php://input'), true);

$email = trim($in['email'] ?? '');
$password = $in['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos']);
    exit;
}

// Buscamos al usuario
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE mail=:m LIMIT 1");
$stmt->execute(['m' => $email]);
$u = $stmt->fetch();

if (!$u) {
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

// Verificamos contraseña
$esValido = false;
if (password_verify($password, $u['clave'])) {
    $esValido = true;
} elseif ($password === $u['clave']) {
    // Migración de contraseñas viejas
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE Usuario SET clave=? WHERE ID_Usuario=?")->execute([$newHash, $u['ID_Usuario']]);
    $esValido = true;
}

if ($esValido) {
    // Nueva sesion al autenticar (evita fijacion de sesion). La identidad y los permisos se
    // revalidan contra la base en cada pedido (sesion.php); aca solo se guarda el ID.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['ID_Usuario'];
    $_SESSION['es_admin'] = $u['es_admin'] ?? 'N';

    // Para un admin, "su" gimnasio es el que posee. Se informa al frontend solo para la interfaz;
    // el servidor nunca lo toma del cliente.
    $idGimnasioUi = isset($u['ID_Gimnasio']) && $u['ID_Gimnasio'] !== null ? (int) $u['ID_Gimnasio'] : null;
    if (($u['es_admin'] ?? 'N') === 'S') {
        $stmtDueno = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE ID_Usuario_Dueno = ? AND activo = 'S' LIMIT 1");
        $stmtDueno->execute([(int) $u['ID_Usuario']]);
        $gDueno = $stmtDueno->fetch();
        if ($gDueno) {
            $idGimnasioUi = (int) $gDueno['ID_Gimnasio'];
        }
    }

    echo json_encode([
        'ok' => true,
        'msg' => 'Bienvenido ' . $u['nombre'],
        'user' => [
            'ID_Usuario' => (int) $u['ID_Usuario'],
            'nombre' => $u['nombre'],
            'apellido' => $u['apellido'],
            'email' => $u['mail'],
            'foto' => $u['foto'],
            'DNI' => $u['DNI'],
            'telefono' => $u['telefono'],
            'sexo' => $u['sexo'],
            'es_admin' => $u['es_admin'] ?? 'N',
            'ID_Gimnasio' => $idGimnasioUi
        ]
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Contraseña incorrecta']);
}
?>
