<?php
// api/eliminar_historial.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$in = json_decode(file_get_contents('php://input'), true);
$histId = $in['id_historial'] ?? 0;
$uid = $in['user_id'] ?? 0;

if (!$histId || !$uid) { echo json_encode(['ok'=>false, 'msg'=>'Datos faltantes']); exit; }

try {
    // Seguridad: Verificar que el registro pertenezca al usuario que intenta borrarlo
    $stmt = $pdo->prepare("DELETE FROM Historial WHERE ID_Historial = ? AND ID_Usuario = ?");
    $stmt->execute([$histId, $uid]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'msg' => 'Eliminado correctamente']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar o no existe']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>