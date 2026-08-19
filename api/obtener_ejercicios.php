<?php
// api/obtener_ejercicios.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';
try {
    // MODIFICADO: Se borrио ", foto" de la lista
    $stmt = $pdo->query("SELECT ID_Ejercicio, nombre, descripcion, video FROM Ejercicio ORDER BY nombre ASC");
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>