<?php
// api/obtener_historial.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$uid = $_GET['user_id'] ?? 0;

try {
    // AGREGADO: h.ID_Historial para poder borrar
    $sql = "SELECT h.ID_Historial, h.fecha, h.peso, h.series, h.repeticiones, e.nombre as nombre_ejercicio 
            FROM Historial h
            JOIN Ejercicio e ON h.ID_Ejercicio = e.ID_Ejercicio
            WHERE h.ID_Usuario = ? 
            ORDER BY h.fecha DESC LIMIT 50";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>