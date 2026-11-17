<?php
// api/conexion.php - Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

// Las credenciales viven en api/db_config.php (no se versiona). Plantilla: db_config.example.php
$dbConfigPath = __DIR__ . '/db_config.php';
if (!file_exists($dbConfigPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Falta api/db_config.php. Copiá api/db_config.example.php y completá los datos de la base.']);
    exit;
}
$dbConfig = require $dbConfigPath;

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port=" . ($dbConfig['port'] ?? 3306) . ";dbname={$dbConfig['database']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        $options
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de conexion con la base de datos.']);
    exit;
}
?>
