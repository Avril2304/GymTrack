<?php

// GymTrack Bootstrap Dispatcher (Laravel Eloquent ORM & Route Engine)

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/EloquentAutoload.php';
}

use Illuminate\Database\Capsule\Manager as Capsule;

// Cargar variables de entorno desde .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2) + [NULL, NULL];
        if ($name && $value) {
            putenv(trim($name) . '=' . trim($value));
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// Configurar e inicializar Eloquent ORM
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST') ?: 'localhost',
    'database'  => getenv('DB_DATABASE') ?: 'gymtrack_tasty',
    'username'  => getenv('DB_USERNAME') ?: '',
    'password'  => getenv('DB_PASSWORD') ?: '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Helper response() si no está definido por el framework completo
if (!function_exists('response')) {
    function response() {
        return new class {
            public function json($data, $status = 200) {
                http_response_code($status);
                header("Content-Type: application/json; charset=UTF-8");
                echo json_encode($data);
                exit;
            }
        };
    }
}

// Despacho de Rutas de la API
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalizar la URI quitando el prefijo /api si existe
$uri = preg_replace('#^.*/api#', '', $uri);
if (empty($uri)) $uri = '/';

// Enrutador de peticiones hacia Controladores (soporta nombres REST y nombres legacy .php)
switch ($uri) {
    case '/login':
    case '/login.php':
        (new App\Http\Controllers\AuthController)->login(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/register':
    case '/register.php':
        (new App\Http\Controllers\AuthController)->register(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/clases':
    case '/clases.php':
    case '/obtener_clases.php':
        (new App\Http\Controllers\ClaseController)->index();
        break;

    case '/ejercicios':
    case '/ejercicios.php':
    case '/obtener_ejercicios.php':
        (new App\Http\Controllers\EjercicioController)->index();
        break;

    case '/rutinas':
    case '/rutinas.php':
    case '/obtener_rutinas.php':
        (new App\Http\Controllers\RutinaController)->obtenerRutinas(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/mis-rutinas':
    case '/mis_rutinas.php':
    case '/obtener_mis_rutinas.php':
        (new App\Http\Controllers\RutinaController)->misRutinas(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/rutina-detalle':
    case '/rutina_detalle.php':
    case '/obtener_detalle_rutina.php':
        (new App\Http\Controllers\RutinaController)->detalle(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/crear-rutina':
    case '/crear_rutina.php':
    case '/guardar_rutina_usuario.php':
        (new App\Http\Controllers\RutinaController)->store(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/toggle-favorito':
    case '/toggle_favorito.php':
        (new App\Http\Controllers\RutinaController)->toggleFavorito(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/eliminar-rutina':
    case '/eliminar_rutina.php':
        (new App\Http\Controllers\RutinaController)->destroy(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/historial':
    case '/historial.php':
    case '/obtener_historial.php':
        (new App\Http\Controllers\HistorialController)->index(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/guardar-entrenamiento':
    case '/guardar_entrenamiento.php':
        (new App\Http\Controllers\HistorialController)->store(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/eliminar-historial':
    case '/eliminar_historial.php':
        (new App\Http\Controllers\HistorialController)->destroy(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/perfil':
    case '/perfil.php':
    case '/obtener_perfil.php':
        if ($method === 'POST') {
            (new App\Http\Controllers\PerfilController)->update(new Illuminate\Http\Request($_REQUEST));
        } else {
            (new App\Http\Controllers\PerfilController)->show(new Illuminate\Http\Request($_REQUEST));
        }
        break;

    case '/editar-perfil':
    case '/editar_perfil.php':
        (new App\Http\Controllers\PerfilController)->update(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/subir-foto-perfil':
    case '/subir_foto_perfil.php':
        (new App\Http\Controllers\PerfilController)->subirFoto(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/admin/usuarios':
    case '/admin_usuarios.php':
    case '/obtener_usuarios.php':
        (new App\Http\Controllers\AdminController)->usuarios();
        break;

    case '/admin/dashboard':
    case '/admin_dashboard.php':
    case '/dashboard.php':
        (new App\Http\Controllers\AdminController)->dashboard();
        break;

    case '/admin/asignar-gimnasio':
    case '/asignar_gimnasio.php':
        (new App\Http\Controllers\GimnasioController)->asignarGimnasio(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/admin/gimnasios':
    case '/obtener_gimnasios.php':
        (new App\Http\Controllers\GimnasioController)->listar();
        break;

    case '/coach/chat':
    case '/coach-chat':
    case '/coach_chat.php':
    case '/chat_coach.php':
        (new App\Http\Controllers\CoachController)->chat(new Illuminate\Http\Request($_REQUEST));
        break;

    default:
        response()->json(['ok' => false, 'msg' => 'Ruta no encontrada en la API GymTrack'], 404);
        break;
}

