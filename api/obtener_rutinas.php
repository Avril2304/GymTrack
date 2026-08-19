<?php
// api/obtener_rutinas.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$uid = $_GET['user_id'] ?? 0;

try {
    $rutinas = [];
    
    // Validamos que haya un usuario para personalizar la recomendación
    if ($uid > 0) {
        // 1. Obtener la "Clase" (Objetivo) del usuario actual
        $stmtUser = $pdo->prepare("SELECT ID_Clase FROM Usuario WHERE ID_Usuario = ?");
        $stmtUser->execute([$uid]);
        $user = $stmtUser->fetch();
        $idClaseUsuario = $user['ID_Clase'] ?? 0;

        // 2. Consulta INTELIGENTE:
        // - Trae rutinas que coincidan con tu Clase O sean Universales.
        // - CRUCIAL: "AND r.ID_Usuario IS NULL" filtra para que SOLO sean rutinas del sistema.
        $sql = "SELECT r.ID_Rutina, r.nombre, r.descripcion, c.nombre as nombre_clase,
                CASE 
                    WHEN r.ID_Clase = ? THEN 'recomendada'
                    ELSE 'estandar'
                END as tipo
                FROM Rutina r
                JOIN Clase c ON r.ID_Clase = c.ID_Clase
                WHERE (r.ID_Clase = ? OR c.nombre = 'Universal') 
                AND r.ID_Usuario IS NULL 
                ORDER BY tipo DESC, r.nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idClaseUsuario, $idClaseUsuario]);
        $rutinas = $stmt->fetchAll();
    } else {
        // Fallback por si no llega el ID de usuario (Solo muestra sistema)
        $stmt = $pdo->query("SELECT *, 'estandar' as tipo FROM Rutina WHERE ID_Usuario IS NULL");
        $rutinas = $stmt->fetchAll();
    }
    
    echo json_encode(['ok' => true, 'data' => $rutinas]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>