<?php
// api/crear_solicitud.php
// El socio pide unirse a un gimnasio. Body: { identificador }
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    $in = gt_json_entrada();
    gt_responder(gt_solicitud_crear($pdo, $uid, $in['identificador'] ?? ''));
} catch (Exception $e) {
    error_log('crear_solicitud.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
