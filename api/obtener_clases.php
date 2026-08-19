<?php
// api/obtener_clases.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

try {
    // CAMBIO: Ya no filtramos por sexo (WHERE sexo = ?). Traemos TODO.
    // Ordenamos por nombre para que la lista se vea ordenada alfabéticamente
    $sql = "SELECT ID_Clase, nombre, descripcion FROM Clase ORDER BY nombre ASC";
    
    // Ejecutamos la consulta directamente ya que no hay parámetros (?)
    $stmt = $pdo->query($sql);
    
    // Devolvemos los resultados en formato JSON
    echo json_encode([
        'ok' => true,
        'data' => $stmt->fetchAll()
    ]);
} catch (Exception $e) {
    // En caso de error, devolvemos un mensaje descriptivo
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>