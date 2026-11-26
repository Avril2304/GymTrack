<?php
// Copiá este archivo como mail_config.php (NO se versiona) y completá los datos SMTP.
// NUNCA acceder directamente desde la web.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

define('MAIL_HOST',      '');
define('MAIL_PORT',      465);
define('MAIL_USERNAME',  '');
define('MAIL_PASSWORD',  '');
define('MAIL_FROM',      '');
define('MAIL_FROM_NAME', 'GymTrack');
define('MAIL_SECURE',    'ssl');

// Token secreto para proteger los endpoints de cron
define('CRON_TOKEN', '');

// URL publica opcional para los enlaces en correos (si se omite, se detecta automaticamente)
// define('APP_URL', 'https://gymtrack.com.ar');
