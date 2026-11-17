<?php
// api/cancelar_solicitud.php
// El socio cancela su solicitud pendiente.
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_solicitud_cancelar($pdo, $uid));
} catch (Exception $e) {
    error_log('cancelar_solicitud.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
