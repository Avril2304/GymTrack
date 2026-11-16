<?php
// api/salir_gimnasio.php
// El socio sale de su gimnasio.
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_usuario_salir($pdo, $uid));
} catch (Exception $e) {
    error_log('salir_gimnasio.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
