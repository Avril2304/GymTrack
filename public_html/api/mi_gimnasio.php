<?php
// api/mi_gimnasio.php
// Datos del gimnasio del administrador logueado (data = null si todavia no lo creo).
require_once 'gimnasio_lib.php';
gt_cabeceras('GET');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_gimnasio_mio($pdo, $uid));
} catch (Exception $e) {
    error_log('mi_gimnasio.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
