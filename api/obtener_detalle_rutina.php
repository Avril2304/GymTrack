<?php
// api/obtener_detalle_rutina.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$rid = $_GET['id'] ?? 0;
$uid = $_GET['user_id'] ?? 0;

if (!$rid) { echo json_encode(['ok'=>false, 'msg'=>'Falta ID']); exit; }

try {
    // 1. Datos básicos
    $stmt = $pdo->prepare("SELECT * FROM Rutina WHERE ID_Rutina = ?");
    $stmt->execute([$rid]);
    $rutina = $stmt->fetch();

    if (!$rutina) { echo json_encode(['ok'=>false, 'msg'=>'No encontrada']); exit; }

    // 2. ¿Es favorita?
    $esFavorita = false;
    if($uid > 0) {
        $stmtFav = $pdo->prepare("SELECT ID_URD FROM Usuario_RutinaDia WHERE ID_Usuario = ? AND ID_Rutina = ?");
        $stmtFav->execute([$uid, $rid]);
        if($stmtFav->fetch()) $esFavorita = true;
    }

    // 3. Lista de ejercicios
    // MODIFICADO: Se borró "e.foto" de aquí
    $sqlEj = "SELECT e.ID_Ejercicio, e.nombre, e.descripcion, e.video 
              FROM Ejercitacion ej
              JOIN Ejercicio e ON ej.ID_Ejercicio = e.ID_Ejercicio
              WHERE ej.ID_Rutina = ?";
    $stmtEj = $pdo->prepare($sqlEj);
    $stmtEj->execute([$rid]);
    $ejercicios = $stmtEj->fetchAll();

    echo json_encode([
        'ok' => true,
        'rutina' => $rutina,
        'es_favorita' => $esFavorita,
        'ejercicios' => $ejercicios
    ]);

} catch (Exception $e) { echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]); }
?>