<?php
// api/buscar_gimnasio.php
// Busca UN gimnasio por identificador exacto (?identificador=...). No lista ni busca parcial.
require_once 'gimnasio_lib.php';
gt_cabeceras('GET');
gt_iniciar_sesion();
require_once 'conexion.php';

$uid = gt_usuario_id();
try {
    gt_responder(gt_gimnasio_buscar($pdo, $_GET['identificador'] ?? ''));
} catch (Exception $e) {
    error_log('buscar_gimnasio.php: ' . $e->getMessage());
    gt_error(500, 'No se pudo completar la operación.');
}
?>
