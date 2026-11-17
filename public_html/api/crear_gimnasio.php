<?php
// api/crear_gimnasio.php
// El administrador crea SU gimnasio (uno solo). Body: { nombre, direccion?, telefono?, mail?, identificador? }
require_once 'gimnasio_lib.php';
gt_cabeceras('POST');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_gimnasio_crear($pdo, $uid, gt_json_entrada()));
} catch (Exception $e) {
    error_log('crear_gimnasio.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
