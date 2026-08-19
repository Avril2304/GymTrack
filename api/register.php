<?php
// api/register.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'conexion.php';
$in = json_decode(file_get_contents('php://input'), true);

$nombre = trim($in['nombre'] ?? '');
$apellido = trim($in['apellido'] ?? '');
$dni = trim($in['dni'] ?? '');
$fecha_nac = trim($in['fecha_nacimiento'] ?? '');
$email = trim($in['email'] ?? '');
$pass = $in['password'] ?? '';

if (!$nombre || !$apellido || !$dni || !$fecha_nac || !$email || !$pass) {
    echo json_encode(['ok' => false, 'msg' => 'Todos los campos son obligatorios']);
    exit;
}

try {
    // 1. Validar EMAIL duplicado
    $stmt = $pdo->prepare("SELECT ID_Usuario FROM Usuario WHERE mail = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) { echo json_encode(['ok' => false, 'msg' => 'El correo ya existe']); exit; }

    // 2. Validar DNI duplicado
    $stmtDNI = $pdo->prepare("SELECT ID_Usuario FROM Usuario WHERE DNI = ?");
    $stmtDNI->execute([$dni]);
    if ($stmtDNI->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Este DNI ya está registrado']); exit; }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    
    // 3. CAMBIO: Usamos 70.0 y 1.70 (Valores seguros que la BD acepta)
    $sql = "INSERT INTO Usuario (ID_Clase, nombre, apellido, peso, altura, DNI, fecha_nacimiento, sexo, mail, telefono, clave, foto) 
            VALUES (1, ?, ?, 70.0, 1.70, ?, ?, 'M', ?, '', ?, 'default.jpg')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $apellido, $dni, $fecha_nac, $email, $hash]);

    echo json_encode(['ok' => true, 'msg' => 'Cuenta creada con éxito.']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
}
?>