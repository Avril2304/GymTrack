<?php
// api/obtener_mis_rutinas.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$uid = $_GET['user_id'] ?? 0;

try {
    $respuesta = ['ok' => true, 'creadas' => [], 'favoritas' => []];

    // 1. Creadas por TI (Tu propiedad)
    // Buscamos rutinas donde la columna ID_Usuario sea TU ID.
    $stmt1 = $pdo->prepare("SELECT ID_Rutina, nombre, descripcion FROM Rutina WHERE ID_Usuario = ? ORDER BY nombre ASC");
    $stmt1->execute([$uid]);
    $respuesta['creadas'] = $stmt1->fetchAll();

    // 2. Favoritas del SISTEMA (Guardadas en tu lista pero no son tuyas)
    // Buscamos en la tabla Usuario_RutinaDia, pero nos aseguramos de que
    // la rutina original sea del sistema (ID_Usuario IS NULL) para no duplicar.
    $stmt2 = $pdo->prepare("SELECT r.ID_Rutina, r.nombre, r.descripcion 
                            FROM Usuario_RutinaDia urd
                            JOIN Rutina r ON urd.ID_Rutina = r.ID_Rutina
                            WHERE urd.ID_Usuario = ? AND r.ID_Usuario IS NULL
                            ORDER BY r.nombre ASC");
    $stmt2->execute([$uid]);
    $respuesta['favoritas'] = $stmt2->fetchAll();
    
    echo json_encode($respuesta);

} catch (Exception $e) { 
    echo json_encode(['ok'=>false, 'msg' => $e->getMessage()]); 
}
?>