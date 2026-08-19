<?php
// api/subir_foto_perfil.php
header("Access-Control-Allow-Origin: *");
// No ponemos header JSON al principio porque al subir archivos el content-type varía.

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uid = $_POST['user_id'] ?? 0;
    
    // 1. Validar que llegó un archivo
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'msg' => 'Error al recibir el archivo']);
        exit;
    }

    $file = $_FILES['foto'];
    
    // 2. Validaciones de Seguridad (Tipo y Peso)
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $maxSize = 5 * 1024 * 1024; // 5 MB

    if (!in_array($file['type'], $tiposPermitidos)) {
        echo json_encode(['ok' => false, 'msg' => 'Formato no válido (Usa JPG, PNG o WEBP)']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['ok' => false, 'msg' => 'La imagen es muy pesada (Max 5MB)']);
        exit;
    }

    // 3. Generar nombre único para evitar caché
    // Ejemplo: user_15_16789922.jpg
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreArchivo = 'user_' . $uid . '_' . time() . '.' . $ext;
    
    // Ruta destino: Sube un nivel (..) desde /api/ y entra a /img/usuarios/
    $rutaDestino = '../img/usuarios/' . $nombreArchivo;

    if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
        try {
            // 4. Actualizar Base de Datos
            $stmt = $pdo->prepare("UPDATE Usuario SET foto = ? WHERE ID_Usuario = ?");
            $stmt->execute([$nombreArchivo, $uid]);
            
            // Devolvemos el nombre nuevo para que el JS actualice la foto al instante
            echo json_encode(['ok' => true, 'msg' => 'Foto actualizada', 'nueva_foto' => $nombreArchivo]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Error al guardar en el servidor. Revisa permisos de carpeta.']);
    }
}
?>