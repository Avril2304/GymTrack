<?php
// api/actualizar_gimnasio.php
// El administrador actualiza SU gimnasio. Body: { nombre?, direccion?, telefono?, mail?, identificador? }
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_gimnasio_actualizar($pdo, $uid, gt_json_entrada()));
} catch (Exception $e) {
    error_log('actualizar_gimnasio.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
