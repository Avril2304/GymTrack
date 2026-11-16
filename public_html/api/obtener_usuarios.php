<?php
// api/obtener_usuarios.php
// Lista los socios del gimnasio del administrador logueado. El gimnasio sale de la sesion
// del servidor (ver sesion.php); el cliente no puede elegir ni ampliar el filtro.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'sesion.php';
gt_iniciar_sesion();
require_once 'conexion.php';

try {
    $admin = gt_requerir_admin_con_gimnasio($pdo);

    // Columnas explicitas: nunca SELECT u.* (incluiria la clave hasheada y otros campos internos).
    $stmt = $pdo->prepare("
        SELECT u.ID_Usuario, u.ID_Clase, u.ID_Gimnasio, u.nombre, u.apellido, u.peso, u.altura,
               u.DNI, u.fecha_nacimiento, u.fecha_alta, u.sexo, u.mail, u.telefono, u.foto, u.es_admin,
               c.nombre AS nombre_clase, g.nombre AS nombre_gimnasio
        FROM Usuario u
        LEFT JOIN Clase c ON u.ID_Clase = c.ID_Clase
        LEFT JOIN Gimnasio g ON u.ID_Gimnasio = g.ID_Gimnasio
        WHERE u.ID_Gimnasio = :g
        ORDER BY c.nombre ASC, u.apellido ASC, u.nombre ASC
    ");
    $stmt->execute([':g' => $admin['ID_Gimnasio']]);

    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('obtener_usuarios: ' . $e->getMessage());
    gt_error(500, 'Error al obtener usuarios.');
}
?>
