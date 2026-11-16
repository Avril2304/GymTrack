<?php
// api/resolver_solicitud.php
// El administrador aprueba o rechaza una solicitud de SU gimnasio. Body: { id_solicitud, accion: 'aprobar'|'rechazar' }
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    $in = gt_json_entrada();
    gt_responder(gt_solicitud_resolver($pdo, $uid, $in['id_solicitud'] ?? 0, $in['accion'] ?? ''));
} catch (Exception $e) {
    error_log('resolver_solicitud.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
