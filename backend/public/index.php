<?php

// Entry Point de GymTrack API REST (Laravel Architecture & Eloquent ORM)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Resolver la ruta hacia el archivo bootstrap/app.php dentro de la carpeta backend
$bootstrapPath = __DIR__ . '/../../backend/bootstrap/app.php';

if (!file_exists($bootstrapPath)) {
    // Fallback por si la carpeta backend se ubicó al mismo nivel que public_html
    $bootstrapPath = __DIR__ . '/../backend/bootstrap/app.php';
}

if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
} else {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'No se encontró el archivo de inicio backend/bootstrap/app.php. Verifica la estructura de carpetas en cPanel.'
    ]);
    exit;
}
