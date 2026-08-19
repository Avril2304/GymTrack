<?php
// api/guardar_entrenamiento.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$in = json_decode(file_get_contents('php://input'), true);

$uid = $in['user_id'] ?? null;
$items = $in['ejercicios'] ?? []; 
$fechaCliente = $in['fecha'] ?? date('Y-m-d'); // Recibimos la fecha del cliente

if (!$uid || empty($items)) {
    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // CAMBIO IMPORTANTE: Usamos :f (fecha) para insertar la fecha local del usuario
    $stmt = $pdo->prepare("CALL guardar_historial(:u, :e, :f, :s, :r, :p)");

    foreach ($items as $it) {
        $stmt->execute([
            'u' => $uid,
            'e' => $it['id_ejercicio'],
            'f' => $fechaCliente, // Insertamos la fecha que manda el celular
            's' => $it['series'],
            'r' => $it['reps'],
            'p' => $it['peso']
        ]);
        $stmt->closeCursor(); 
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'msg' => 'Entrenamiento guardado']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
?>