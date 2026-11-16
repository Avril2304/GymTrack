<?php
// api/perfil.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'sesion.php';
gt_iniciar_sesion();
require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// ─── Verificar sesión activa ───────────────────────────────────────────────
$sessionUserId = gt_usuario_id();

// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Solo puede ver su propio perfil (o un admin puede ver cualquiera)
    $requestedId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if (!$requestedId) {
        $requestedId = $sessionUserId; // Si no manda user_id, asume el suyo propio
    }

    if ($requestedId !== $sessionUserId) {
        // Ver el perfil de otra persona: solo un admin, y solo si es socio de SU gimnasio.
        $admin = gt_requerir_admin_con_gimnasio($pdo);
        $stmtSocio = $pdo->prepare("SELECT 1 FROM Usuario WHERE ID_Usuario = ? AND ID_Gimnasio = ? LIMIT 1");
        $stmtSocio->execute([$requestedId, $admin['ID_Gimnasio']]);
        if (!$stmtSocio->fetch()) {
            gt_error(403, 'No podés ver el perfil de otro usuario.');
        }
    }

    $uid = $requestedId;

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
                'foto' => $user['foto'],
                'edad' => $edad,
                'dias_entrenados' => $dias
            ]
        ]);
    } catch (Exception $e) { error_log('perfil: ' . $e->getMessage()); gt_error(500, 'Error al procesar el perfil.'); }

} elseif ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);

    // El user_id siempre viene de la sesión del servidor; ignoramos el del cliente
    $uid = $sessionUserId;

    try {
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
    } catch (Exception $e) { error_log('perfil: ' . $e->getMessage()); gt_error(500, 'Error al procesar el perfil.'); }
}
?>
