<?php
// api/buscar_usuario.php
// El administrador busca a una persona por mail exacto (?mail=...) para sumarla con asignar_gimnasio.
require_once 'gimnasio_lib.php';
gt_cabeceras('GET');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_usuario_buscar($pdo, $uid, $_GET['mail'] ?? ''));
} catch (Exception $e) {
    error_log('buscar_usuario.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
