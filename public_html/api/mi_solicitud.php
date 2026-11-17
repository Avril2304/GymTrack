<?php
// api/mi_solicitud.php
// Gimnasio actual del socio y su ultima solicitud.
require_once 'gimnasio_lib.php';
gt_cabeceras('GET');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_solicitud_mia($pdo, $uid));
} catch (Exception $e) {
    error_log('mi_solicitud.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
