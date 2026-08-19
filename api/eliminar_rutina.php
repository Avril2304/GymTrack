<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$in = json_decode(file_get_contents('php://input'), true);
$uid = $in['user_id'] ?? 0;
$rid = $in['id_rutina'] ?? 0;

if (!$uid || !$rid) { 
    echo json_encode(['ok'=>false, 'msg'=>'Faltan datos']); 
    exit; 
}

try {
    // 1. Verificar que la rutina pertenezca al usuario (Seguridad)
    $stmtCheck = $pdo->prepare("SELECT ID_Rutina FROM Rutina WHERE ID_Rutina = ? AND ID_Usuario = ?");
    $stmtCheck->execute([$rid, $uid]);
    
    if (!$stmtCheck->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'No autorizado o rutina no encontrada']);
        exit;
    }

    $pdo->beginTransaction();

    // 2. PASO CRÍTICO: Borrar primero los ejercicios de esa rutina
    // Esto evita el error de "Foreign Key Constraint"
    $stmtEj = $pdo->prepare("DELETE FROM Ejercitacion WHERE ID_Rutina = ?");
    $stmtEj->execute([$rid]);

    // 3. Borrar la relación con favoritos (Usuario_RutinaDia) si existiera
    // (Aunque tu BD tiene CASCADE aquí, es más seguro hacerlo explícito por si acaso)
    $stmtFav = $pdo->prepare("DELETE FROM Usuario_RutinaDia WHERE ID_Rutina = ?");
    $stmtFav->execute([$rid]);

    // 4. Finalmente, borrar la Rutina padre
    $stmtRutina = $pdo->prepare("DELETE FROM Rutina WHERE ID_Rutina = ? AND ID_Usuario = ?");
    $stmtRutina->execute([$rid, $uid]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'msg' => 'Rutina eliminada correctamente']);

} catch (Exception $e) { 
    $pdo->rollBack();
    // Mostramos el mensaje exacto para depurar si sigue fallando
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]); 
}
?>