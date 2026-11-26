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

// ---------------------------------------------------------------------------
// BARRERA DE SESION (defensa central)
// La identidad sale SIEMPRE de la sesion del servidor. Las rutas con datos de usuario exigen
// sesion y se les pisa cualquier user_id que mande el cliente, asi nadie puede leer ni escribir
// a nombre de otro aunque el controlador todavia lea el id del request.
// Publicas (sin sesion): login, registro y catalogos. Admin y gimnasios validan sesion y permisos
// por su cuenta (ver AdminController, GimnasioController y SolicitudController).
// ---------------------------------------------------------------------------
require_once dirname(__DIR__, 2) . '/public_html/api/sesion.php';
gt_iniciar_sesion();

$rutasPublicas = [
    '/', '/login', '/login.php', '/logout', '/logout.php', '/register', '/register.php',
    '/verificar-email', '/verificar_email.php', '/reenviar-verificacion', '/reenviar_verificacion.php',
    '/clases', '/clases.php', '/obtener_clases.php',
    '/ejercicios', '/ejercicios.php', '/obtener_ejercicios.php',
];
$rutasConControlPropio = [
    '/admin_usuarios.php', '/obtener_usuarios.php', '/admin_dashboard.php', '/dashboard.php',
    '/asignar_gimnasio.php', '/obtener_gimnasios.php', '/crear_gimnasio.php', '/mi_gimnasio.php',
    '/actualizar_gimnasio.php',
    '/buscar_gimnasio.php', '/salir_gimnasio.php', '/crear_solicitud.php', '/mi_solicitud.php',
    '/cancelar_solicitud.php', '/solicitudes_pendientes.php', '/resolver_solicitud.php', '/buscar_usuario.php',
];
$tieneControlPropio = in_array($uri, $rutasConControlPropio, true)
    || strpos($uri, '/admin/') === 0 || strpos($uri, '/gimnasio/') === 0 || strpos($uri, '/solicitudes/') === 0;

if (!in_array($uri, $rutasPublicas, true) && !$tieneControlPropio) {
    $sesionUid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($sesionUid <= 0) {
        response()->json(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión primero.'], 401);
    }
    foreach (['user_id', 'ID_Usuario'] as $clave) {
        $_GET[$clave] = $_POST[$clave] = $_REQUEST[$clave] = $sesionUid;
    }
}

// Enrutador de peticiones hacia Controladores (soporta nombres REST y nombres legacy .php)
switch ($uri) {
    case '/login':
    case '/login.php':
        (new App\Http\Controllers\AuthController)->login(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/logout':
    case '/logout.php':
        (new App\Http\Controllers\AuthController)->logout();
        break;

    case '/register':
    case '/register.php':
        (new App\Http\Controllers\AuthController)->register(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/verificar-email':
    case '/verificar_email.php':
        (new App\Http\Controllers\AuthController)->verificarEmail(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/reenviar-verificacion':
    case '/reenviar_verificacion.php':
        (new App\Http\Controllers\AuthController)->reenviarVerificacion(new Illuminate\Http\Request($_REQUEST));
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

    // Gimnasio propio y alta de socios (reglas compartidas en public_html/api/gimnasio_lib.php)
    case '/gimnasio/crear':
    case '/crear_gimnasio.php':
        (new App\Http\Controllers\SolicitudController)->crearGimnasio(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/gimnasio/actualizar':
    case '/actualizar_gimnasio.php':
        (new App\Http\Controllers\SolicitudController)->actualizarGimnasio(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/gimnasio/mio':
    case '/mi_gimnasio.php':
        (new App\Http\Controllers\SolicitudController)->miGimnasio();
        break;

    case '/gimnasio/buscar':
    case '/buscar_gimnasio.php':
        (new App\Http\Controllers\SolicitudController)->buscarGimnasio(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/gimnasio/salir':
    case '/salir_gimnasio.php':
        (new App\Http\Controllers\SolicitudController)->salirGimnasio();
        break;

    case '/solicitudes/crear':
    case '/crear_solicitud.php':
        (new App\Http\Controllers\SolicitudController)->crear(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/solicitudes/mia':
    case '/mi_solicitud.php':
        (new App\Http\Controllers\SolicitudController)->mia();
        break;

    case '/solicitudes/cancelar':
    case '/cancelar_solicitud.php':
        (new App\Http\Controllers\SolicitudController)->cancelar();
        break;

    case '/solicitudes/pendientes':
    case '/solicitudes_pendientes.php':
        (new App\Http\Controllers\SolicitudController)->pendientes();
        break;

    case '/solicitudes/resolver':
    case '/resolver_solicitud.php':
        (new App\Http\Controllers\SolicitudController)->resolver(new Illuminate\Http\Request($_REQUEST));
        break;

    case '/admin/buscar-usuario':
    case '/buscar_usuario.php':
        (new App\Http\Controllers\SolicitudController)->buscarUsuario(new Illuminate\Http\Request($_REQUEST));
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

