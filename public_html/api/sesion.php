<?php
// api/sesion.php - Identidad y permisos del lado del servidor.
// Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
//
// REGLA: la identidad de quien llama sale SIEMPRE de $_SESSION (y se revalida contra la
// base). Nunca se lee de parametros del cliente (admin_id, user_id, ?id_gimnasio=, etc.).
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

function gt_iniciar_sesion() {
    if (session_status() === PHP_SESSION_NONE) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// Corta la ejecucion con un error JSON. $codigo permite que el frontend distinga casos.
function gt_error($status, $msg, $codigo = null) {
    http_response_code($status);
    $body = ['ok' => false, 'msg' => $msg];
    if ($codigo !== null) {
        $body['codigo'] = $codigo;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// ID del usuario logueado segun la sesion del servidor (401 si no hay sesion).
function gt_usuario_id() {
    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id <= 0) {
        gt_error(401, 'No autorizado. Iniciá sesión primero.');
    }
    return $id;
}

// Exige un administrador (revalidado en la base). Devuelve ['ID_Usuario' => int, 'ID_Gimnasio' => int|null].
// El gimnasio del admin es el que posee (Gimnasio.ID_Usuario_Dueno): esa es la unica fuente de verdad.
function gt_requerir_admin(PDO $pdo) {
    $id = gt_usuario_id();

    $stmt = $pdo->prepare("SELECT es_admin FROM Usuario WHERE ID_Usuario = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u || ($u['es_admin'] ?? 'N') !== 'S') {
        gt_error(403, 'Acceso denegado: se requieren permisos de administrador.');
    }

    $stmt = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE ID_Usuario_Dueno = ? AND activo = 'S' LIMIT 1");
    $stmt->execute([$id]);
    $g = $stmt->fetch();

    return [
        'ID_Usuario'  => $id,
        'ID_Gimnasio' => $g ? (int) $g['ID_Gimnasio'] : null,
    ];
}

// Igual que gt_requerir_admin, pero ademas exige que el admin ya tenga un gimnasio.
// Un admin sin gimnasio NO ve datos de nadie (nunca "todos").
function gt_requerir_admin_con_gimnasio(PDO $pdo) {
    $admin = gt_requerir_admin($pdo);
    if ($admin['ID_Gimnasio'] === null) {
        gt_error(403, 'Tu cuenta de administrador todavía no tiene un gimnasio asignado.', 'ADMIN_SIN_GIMNASIO');
    }
    return $admin;
}
