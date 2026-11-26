<?php
// api/verificar_email.php
// Confirma el correo electronico de un usuario a partir del token enviado por mail.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/mailer_lib.php';

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = [];
}

try {
    $res = gt_verificacion_confirmar($pdo, $in['token'] ?? '');
    http_response_code($res['status']);
    echo json_encode($res['body'], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('verificar_email.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'No se pudo completar la verificación.'], JSON_UNESCAPED_UNICODE);
}
