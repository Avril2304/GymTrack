<?php
// Copiá este archivo como db_config.php (NO se versiona) y completá los datos reales.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

return [
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'gymtrack_tasty',
    'user'     => '',
    'password' => '',
];
