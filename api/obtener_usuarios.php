<?php
// api/obtener_usuarios.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

try {
    // 1. Seleccionamos TODO del usuario (u.*)
    // 2. Traemos el nombre de la clase (c.nombre) usando LEFT JOIN
    // 3. Ordenamos primero por Clase y luego por Apellido
    $sql = "SELECT u.*, c.nombre as nombre_clase 
            FROM Usuario u 
            LEFT JOIN Clase c ON u.ID_Clase = c.ID_Clase 
            ORDER BY c.nombre ASC, u.apellido ASC, u.nombre ASC";

    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll();

    // Enviamos la lista al frontend
    echo json_encode([
        'ok' => true,
        'data' => $usuarios
    ]);

} catch (Exception $e) {
    // Si algo falla, avisamos
    echo json_encode([
        'ok' => false, 
        'msg' => 'Error al obtener usuarios: ' . $e->getMessage()
    ]);
}
?>