<?php
// api/asignar_gimnasio.php
// Herramienta directa del administrador: sumar un socio libre a SU gimnasio, o sacar a un
// socio de SU gimnasio. Body: { user_id, id_gimnasio }  (id_gimnasio = null para desvincular).
// La identidad del admin sale de la sesion del servidor; admin_id del cliente se ignora.
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
require_once __DIR__ . '/movimientos.php'; // altas y bajas de socios (Movimiento_Socio)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gt_error(405, 'Método no permitido.');
}

// 1. Autenticacion y permisos primero (401/403), antes de revelar si algo existe (404).
$admin = gt_requerir_admin_con_gimnasio($pdo);
$miGym = $admin['ID_Gimnasio'];

// 2. Validacion de la entrada
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = [];
}
$userId = isset($in['user_id']) ? (int) $in['user_id'] : 0;
if ($userId <= 0 || !array_key_exists('id_gimnasio', $in)) {
    gt_error(400, 'Faltan campos obligatorios: user_id e id_gimnasio.');
}
$destino = ($in['id_gimnasio'] === null || $in['id_gimnasio'] === '') ? null : (int) $in['id_gimnasio'];

// Un admin solo puede conectar socios a SU gimnasio.
if ($destino !== null && $destino !== $miGym) {
    gt_error(403, 'Solo podés asignar socios a tu propio gimnasio.');
}

try {
    // 3. Lectura bloqueada del usuario destino para decidir sin carreras.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT ID_Gimnasio, es_admin FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if ($destino !== null) {
        // CONECTAR: el socio tiene que existir y estar libre (o ya ser de mi gimnasio).
        if (!$target) {
            $pdo->rollBack();
            gt_error(404, 'El usuario indicado no existe.');
        }
        if (($target['es_admin'] ?? 'N') === 'S') {
            $pdo->rollBack();
            gt_error(409, 'No se puede asignar a un administrador como socio.');
        }
        $actual = $target['ID_Gimnasio'] !== null ? (int) $target['ID_Gimnasio'] : null;
        if ($actual !== null && $actual !== $miGym) {
            $pdo->rollBack();
            gt_error(409, 'Ese usuario ya pertenece a otro gimnasio.');
        }
        if ($actual === null) {
            $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = ? WHERE ID_Usuario = ?")->execute([$miGym, $userId]);
            gt_registrar_movimiento($pdo, $userId, $miGym, 'alta', 'admin');
            // Si tenia una solicitud pendiente (a este u otro gimnasio), queda sin efecto.
            $pdo->prepare("UPDATE Solicitud SET estado = 'cancelada', fecha_resolucion = NOW() WHERE ID_Usuario = ? AND estado = 'pendiente'")->execute([$userId]);
        }
    } else {
        // DESVINCULAR: solo socios de mi gimnasio. Mismo error si no existe o es de otro gimnasio,
        // para no revelar a quien pertenece un usuario ajeno.
        if (!$target || $target['ID_Gimnasio'] === null || (int) $target['ID_Gimnasio'] !== $miGym) {
            $pdo->rollBack();
            gt_error(404, 'Ese usuario no pertenece a tu gimnasio.');
        }
        $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = NULL WHERE ID_Usuario = ?")->execute([$userId]);
        gt_registrar_movimiento($pdo, $userId, $miGym, 'baja', 'admin');
    }
    $pdo->commit();

    echo json_encode([
        'ok'  => true,
        'msg' => $destino !== null ? 'Usuario conectado al gimnasio exitosamente.' : 'Usuario desvinculado del gimnasio exitosamente.',
        'data' => ['ID_Usuario' => $userId, 'ID_Gimnasio' => $destino]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('asignar_gimnasio: ' . $e->getMessage());
    gt_error(500, 'No se pudo actualizar el gimnasio del usuario.');
}
?>
