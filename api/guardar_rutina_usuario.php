<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$in = json_decode(file_get_contents('php://input'), true);
$uid = $in['user_id'] ?? 0;
$nombre = trim($in['nombre'] ?? '');
$ejercicios = $in['ejercicios'] ?? []; 
$rutinaId = $in['id_rutina'] ?? 0;

if (!$uid || !$nombre || empty($ejercicios)) { echo json_encode(['ok' => false, 'msg' => 'Faltan datos']); exit; }

try {
    $pdo->beginTransaction();
    if ($rutinaId > 0) {
        // EDICIÓN
        $check = $pdo->prepare("SELECT ID_Rutina FROM Rutina WHERE ID_Rutina = ? AND ID_Usuario = ?");
        $check->execute([$rutinaId, $uid]);
        if (!$check->fetch()) throw new Exception("No autorizado");

        $pdo->prepare("UPDATE Rutina SET nombre = ? WHERE ID_Rutina = ?")->execute([$nombre, $rutinaId]);
        $pdo->prepare("DELETE FROM Ejercitacion WHERE ID_Rutina = ?")->execute([$rutinaId]);
        $targetId = $rutinaId; $msg = "Actualizada";
    } else {
        // CREACIÓN
        $pdo->prepare("INSERT INTO Rutina (ID_Clase, ID_Usuario, nombre, descripcion) VALUES (1, ?, ?, 'Personalizada')")->execute([$uid, $nombre]);
        $targetId = $pdo->lastInsertId();
        
        // Auto-fav (CORREGIDO: Eliminado ", dia" y ", 0")
        $pdo->prepare("INSERT INTO Usuario_RutinaDia (ID_Usuario, ID_Rutina) VALUES (?, ?)")->execute([$uid, $targetId]);
        $msg = "Creada";
    }
    
    // Insertar Ejercicios (CORREGIDO: Sin series/reps)
    $ins = $pdo->prepare("INSERT INTO Ejercitacion (ID_Rutina, ID_Ejercicio) VALUES (?, ?)");
    foreach ($ejercicios as $e) $ins->execute([$targetId, $e]);
    
    $pdo->commit(); 
    echo json_encode(['ok' => true, 'msg' => $msg]);
} catch (Exception $e) { 
    $pdo->rollBack(); 
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); 
}
?>