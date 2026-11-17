<?php
// Copiá este archivo como config.php (NO se versiona) y completá tus claves.
// Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

// ExerciseDB (RapidAPI)
define('RAPIDAPI_HOST', '');
define('RAPIDAPI_KEY', '');

// Coach Virtual (OpenAI). Se puede definir por variable de entorno o acá.
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');
