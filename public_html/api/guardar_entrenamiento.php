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

    // Auto-llenado de Asistencia (aislado en su propio try/catch para resiliencia total)
    try {
        $hora = $in['hora'] ?? date('H:i:s');
        $turno = ($hora < '14:00:00') ? 'M' : 'T';

        // Traer el gimnasio del socio (puede ser NULL)
        $stmtGym = $pdo->prepare("SELECT ID_Gimnasio FROM Usuario WHERE ID_Usuario = :u");
        $stmtGym->execute(['u' => $uid]);
        $gymRow = $stmtGym->fetch(PDO::FETCH_ASSOC);
        $idGimnasio = ($gymRow && isset($gymRow['ID_Gimnasio'])) ? $gymRow['ID_Gimnasio'] : null;

        // Upsert de asistencia del día
        $stmtAsist = $pdo->prepare("
            INSERT INTO Asistencia (ID_Usuario, ID_Gimnasio, fecha, hora_entrada, turno)
            VALUES (:u, :g, :f, :h, :t)
            ON DUPLICATE KEY UPDATE hora_entrada = LEAST(IFNULL(hora_entrada, VALUES(hora_entrada)), VALUES(hora_entrada))
        ");
        $stmtAsist->execute([
            'u' => $uid,
            'g' => $idGimnasio,
            'f' => $fechaCliente,
            'h' => $hora,
            't' => $turno
        ]);
    } catch (Throwable $eAsist) {
        // Si falla asistencia, NO se pierde el entrenamiento ya guardado; solo se loguea
        error_log("Error registrando asistencia automática: " . $eAsist->getMessage());
    }

    echo json_encode(['ok' => true, 'msg' => 'Entrenamiento guardado']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
?>