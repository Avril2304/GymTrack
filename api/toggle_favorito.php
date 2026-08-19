<?php
// api/toggle_favorito.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$in = json_decode(file_get_contents('php://input'), true);
$uid = $in['user_id'] ?? 0;
$rid = $in['rutina_id'] ?? 0;

if (!$uid || !$rid) { echo json_encode(['ok'=>false]); exit; }

try {
    // Verificar si ya existe
    $check = $pdo->prepare("SELECT ID_URD FROM Usuario_RutinaDia WHERE ID_Usuario=? AND ID_Rutina=?");
    $check->execute([$uid, $rid]);
    $existe = $check->fetch();

    if ($existe) {
        // Si existe, BORRAR
        $del = $pdo->prepare("DELETE FROM Usuario_RutinaDia WHERE ID_Usuario=? AND ID_Rutina=?");
        $del->execute([$uid, $rid]);
        echo json_encode(['ok'=>true, 'accion'=>'eliminado', 'msg'=>'Eliminada de mis rutinas']);
    } else {
        // Si no existe, AGREGAR (CORREGIDO: Sin columna dia)
        $ins = $pdo->prepare("INSERT INTO Usuario_RutinaDia (ID_Usuario, ID_Rutina) VALUES (?, ?)");
        $ins->execute([$uid, $rid]);
        echo json_encode(['ok'=>true, 'accion'=>'agregado', 'msg'=>'Agregada a mis rutinas']);
    }
} catch (Exception $e) { echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]); }
?>