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

    // Solo para los scripts de public_html/demo/ (ver docs/DEMO.md). Sin 'demo_token' quedan deshabilitados.
    // 'demo_token'            => '',  // minimo 16 caracteres; se manda como ?token=
    // 'demo_clave_admin'      => '',  // minimo 8; clave de los administradores demo (examen@example.com, etc.)
    // 'demo_gimnasio_actual'  => '',  // identificador del gimnasio real que la demo debe respetar
    // 'limpiar_conservar'     => [],  // mails que limpiar_base.php no borra
];
