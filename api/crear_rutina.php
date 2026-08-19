<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';
$in = json_decode(file_get_contents('php://input'), true);
$uid = $in['user_id'] ?? null; 
$nombre = trim($in['nombre'] ?? ''); 
$ejercicios = $in['ejercicios'] ?? [];

if (!$uid || !$nombre || empty($ejercicios)) { echo json_encode(['ok' => false, 'msg' => 'Faltan datos']); exit; }

try {
    $pdo->beginTransaction();
    // 1. Crear la Rutina
    $pdo->prepare("INSERT INTO Rutina (ID_Clase, ID_Usuario, nombre, descripcion) VALUES (1, ?, ?, 'Personalizada')")->execute([$uid, $nombre]);
    $newId = $pdo->lastInsertId();
    
    // 2. Insertar ejercicios (SIN series, SIN reps)
    $ins = $pdo->prepare("INSERT INTO Ejercitacion (ID_Rutina, ID_Ejercicio) VALUES (?, ?)");
    foreach ($ejercicios as $e) $ins->execute([$newId, $e]);
    
    // 3. Guardar en mis rutinas (SIN columna 'dia')
    $pdo->prepare("INSERT INTO Usuario_RutinaDia (ID_Usuario, ID_Rutina) VALUES (?, ?)")->execute([$uid, $newId]);
    
    $pdo->commit(); 
    echo json_encode(['ok' => true]);
} catch (Exception $e) { 
    $pdo->rollBack(); 
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); 
}
?>