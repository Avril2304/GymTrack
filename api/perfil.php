<?php
// api/perfil.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $uid = $_GET['user_id'] ?? 0;
    try {
        $sql = "SELECT u.*, c.nombre as nombre_clase 
                FROM Usuario u 
                LEFT JOIN Clase c ON u.ID_Clase = c.ID_Clase 
                WHERE u.ID_Usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) { echo json_encode(['ok' => false, 'msg' => 'No encontrado']); exit; }

        $stmtHist = $pdo->prepare("SELECT COUNT(DISTINCT fecha) as total FROM Historial WHERE ID_Usuario = ?");
        $stmtHist->execute([$uid]);
        $dias = $stmtHist->fetch()['total'];

        $edad = '--';
        if ($user['fecha_nacimiento'] && $user['fecha_nacimiento'] !== '0000-00-00') {
            $edad = (new DateTime())->diff(new DateTime($user['fecha_nacimiento']))->y;
        }

        echo json_encode([
            'ok' => true,
            'data' => [
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'nombre_completo' => trim($user['nombre'] . ' ' . $user['apellido']),
                'email' => $user['mail'],
                'peso' => $user['peso'],
                'altura' => $user['altura'],
                'dni' => $user['DNI'],
                'telefono' => $user['telefono'],
                'sexo' => $user['sexo'],
                'fecha_nacimiento' => $user['fecha_nacimiento'],
                'nombre_clase' => $user['nombre_clase'],
                'id_clase' => $user['ID_Clase'],
                'foto' => $user['foto'], // <--- AQUÍ ESTÁ LA CLAVE
                'edad' => $edad,
                'dias_entrenados' => $dias
            ]
        ]);
    } catch (Exception $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
}

elseif ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $uid = $in['user_id'] ?? null;
    if (!$uid) { echo json_encode(['ok' => false]); exit; }

    try {
        // DETECCION: Si viene 'telefono', es Onboarding. Si no, es Edición Normal.
        if (isset($in['telefono'])) {
             // ONBOARDING
             $sql = "UPDATE Usuario SET telefono=?, sexo=?, peso=?, altura=?, ID_Clase=? WHERE ID_Usuario=?";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([$in['telefono'], $in['sexo'], $in['peso'], $in['altura'], $in['id_clase'], $uid]);
        } else {
             // EDICIÓN PERFIL
             $clase = $in['id_clase'] ?? 1;
             $sql = "UPDATE Usuario SET nombre=?, apellido=?, peso=?, altura=?, ID_Clase=? WHERE ID_Usuario=?";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([$in['nombre'], $in['apellido'], $in['peso'], $in['altura'], $clase, $uid]);
        }

        // Recuperar usuario actualizado
        $stmt = $pdo->prepare("SELECT * FROM Usuario WHERE ID_Usuario = ?");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();

        if (!$u) { echo json_encode(['ok' => false, 'msg' => 'Error al recuperar perfil actualizado']); exit; }

        echo json_encode([
            'ok' => true,
            'user' => [
                'ID_Usuario' => $u['ID_Usuario'],
                'nombre' => $u['nombre'],
                'apellido' => $u['apellido'],
                'email' => $u['mail'],
                'foto' => $u['foto'],
                'DNI' => $u['DNI'],
                'fecha_nacimiento' => $u['fecha_nacimiento'],
                'telefono' => $u['telefono'],
                'sexo' => $u['sexo'],
                'ID_Clase' => $u['ID_Clase'],
                'peso' => $u['peso'],
                'altura' => $u['altura']
            ]
        ]);
    } catch (Exception $e) { echo json_encode(['ok' => false, 'msg' => $e->getMessage()]); }
}
?>