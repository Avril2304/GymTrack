<?php
// api/solicitudes_pendientes.php
// Solicitudes pendientes del gimnasio del administrador logueado.
require_once 'gimnasio_lib.php';
gt_cabeceras('GET');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_solicitudes_pendientes($pdo, $uid));
} catch (Exception $e) {
    error_log('solicitudes_pendientes.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
