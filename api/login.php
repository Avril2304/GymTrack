<?php
// api/login.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'conexion.php';
$in = json_decode(file_get_contents('php://input'), true);

$email = trim($in['email'] ?? '');
$password = $in['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos']);
    exit;
}

// BUSCAMOS AL USUARIO
// IMPORTANTE: Ahora seleccionamos TODOS los campos necesarios (incluyendo DNI)
// para que la App sepa que el perfil ya está completo.
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE mail=:m LIMIT 1");
$stmt->execute(['m' => $email]);
$u = $stmt->fetch();

if (!$u) {
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

// VERIFICAMOS CONTRASEÑA
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
    echo json_encode([
        'ok' => true,
        'msg' => 'Bienvenido ' . $u['nombre'],
        'user' => [
            'ID_Usuario' => $u['ID_Usuario'],
            'nombre' => $u['nombre'],
            'apellido' => $u['apellido'],
            'email' => $u['mail'],
            'foto' => $u['foto'],
            'DNI' => $u['DNI'],
            'telefono' => $u['telefono'],
            'sexo' => $u['sexo'],
            'es_admin' => $u['es_admin'] // <--- ¡ESTA ES LA LÍNEA NUEVA IMPORTANTE!
        ]
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Contraseña incorrecta']);
}
?>