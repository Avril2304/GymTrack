<?php
// api/gimnasio_lib.php - Reglas de negocio de gimnasios y solicitudes de ingreso.
// Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
//
// Lo usan los endpoints PHP de api/ y los controladores de backend/ (capa MVC), asi la logica
// transaccional existe una sola vez. Las funciones NO imprimen ni cortan: devuelven
// ['status' => int, 'body' => array] y cada capa decide como responder.
//
// REGLAS DE SEGURIDAD
//  - Quien llama se identifica SOLO por la sesion del servidor (id que recibe cada funcion).
//  - Lock siempre en este orden: Usuario -> Solicitud (evita deadlocks entre endpoints).
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/movimientos.php'; // altas y bajas de socios (Movimiento_Socio)
require_once __DIR__ . '/mailer_lib.php';  // avisos de cambio de gimnasio

define('GT_COOLDOWN_DIAS', 7);          // espera tras un rechazo antes de pedir de nuevo al mismo gimnasio
define('GT_MAX_BUSQUEDAS_GYM', 15);     // buscar_gimnasio: intentos por minuto y sesion
define('GT_MAX_BUSQUEDAS_USUARIO', 20); // buscar_usuario: intentos por minuto y sesion

// ---------------------------------------------------------------- respuestas

function gt_res($status, array $body) {
    return ['status' => $status, 'body' => $body];
}

function gt_res_ok($msg, $data = null, $status = 200) {
    $body = ['ok' => true, 'msg' => $msg];
    if ($data !== null) {
        $body['data'] = $data;
    }
    return gt_res($status, $body);
}

function gt_res_error($status, $msg, $codigo = null) {
    $body = ['ok' => false, 'msg' => $msg];
    if ($codigo !== null) {
        $body['codigo'] = $codigo;
    }
    return gt_res($status, $body);
}

// Imprime la respuesta y corta (capa de endpoints PHP).
function gt_responder(array $res) {
    http_response_code($res['status']);
    echo json_encode($res['body'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Cabeceras comunes de un endpoint: CORS, JSON, OPTIONS y metodo permitido (GET o POST).
function gt_cabeceras($metodo) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: $metodo, OPTIONS");
    header("Content-Type: application/json; charset=UTF-8");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        gt_error(405, 'Método no permitido.');
    }
}

function gt_json_entrada() {
    $in = json_decode(file_get_contents('php://input'), true);
    return is_array($in) ? $in : [];
}

// ---------------------------------------------------------------- utilidades

// Corre $fn dentro de una transaccion. Hace commit si $fn vuelve normalmente (incluso con una
// respuesta de error, que no escribio nada o escribio algo que debe quedar) y rollback si lanza.
function gt_tx(PDO $pdo, callable $fn) {
    $pdo->beginTransaction();
    try {
        $res = $fn();
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return $res;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Limite simple por sesion: maximo $max intentos cada $ventana segundos. Frena el barrido de
// identificadores/mails. No reemplaza un limite por cuenta o IP, pero pone friccion.
function gt_throttle($clave, $max, $ventana = 60) {
    $k = 'throttle_' . $clave;
    $ahora = time();
    $historial = [];
    foreach (($_SESSION[$k] ?? []) as $t) {
        if ($t > $ahora - $ventana) {
            $historial[] = $t;
        }
    }
    if (count($historial) >= $max) {
        $_SESSION[$k] = $historial;
        return false;
    }
    $historial[] = $ahora;
    $_SESSION[$k] = $historial;
    return true;
}

function gt_largo($s) {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function gt_normalizar_identificador($s) {
    return strtolower(trim((string) $s));
}

// 3 a 60 caracteres: minusculas, numeros y guiones intermedios (sin guiones al borde ni dobles).
function gt_identificador_valido($s) {
    return strlen($s) >= 3 && strlen($s) <= 60 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) === 1;
}

// "Gimnasio Central Ñandú" -> "gimnasio-central-nandu"
function gt_slug($texto) {
    $t = function_exists('mb_strtolower') ? mb_strtolower(trim($texto), 'UTF-8') : strtolower(trim($texto));
    $t = strtr($t, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ]);
    $t = trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
    $t = trim(substr($t, 0, 50), '-');
    return strlen($t) >= 3 ? $t : 'gimnasio';
}

// Primer identificador libre a partir de $base: base, base-2, base-3, ...
function gt_identificador_libre(PDO $pdo, $base) {
    $cand = $base;
    $st = $pdo->prepare("SELECT 1 FROM Gimnasio WHERE identificador = ? LIMIT 1");
    for ($i = 2; $i <= 60; $i++) {
        $st->execute([$cand]);
        if (!$st->fetch()) {
            return $cand;
        }
        $cand = $base . '-' . $i;
    }
    return null;
}

// Admin revalidado en la base + su gimnasio (el que posee). Devuelve:
//   ['error' => respuesta]                      si no es admin
//   ['gimnasio' => int|null]                    si es admin (null = todavia no creo su gimnasio)
function gt_lib_admin(PDO $pdo, $adminId) {
    $st = $pdo->prepare("SELECT es_admin FROM Usuario WHERE ID_Usuario = ? LIMIT 1");
    $st->execute([(int) $adminId]);
    $u = $st->fetch();
    if (!$u || ($u['es_admin'] ?? 'N') !== 'S') {
        return ['error' => gt_res_error(403, 'Acceso denegado: se requieren permisos de administrador.')];
    }
    $st = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE ID_Usuario_Dueno = ? AND activo = 'S' LIMIT 1");
    $st->execute([(int) $adminId]);
    $g = $st->fetch();
    return ['gimnasio' => $g ? (int) $g['ID_Gimnasio'] : null];
}

// Igual que gt_lib_admin pero exige que ya tenga gimnasio.
function gt_lib_admin_con_gimnasio(PDO $pdo, $adminId) {
    $ctx = gt_lib_admin($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx;
    }
    if ($ctx['gimnasio'] === null) {
        return ['error' => gt_res_error(403, 'Tu cuenta de administrador todavía no tiene un gimnasio asignado.', 'ADMIN_SIN_GIMNASIO')];
    }
    return $ctx;
}

// ---------------------------------------------------------------- gimnasio (admin)

// POST: el admin crea SU gimnasio (uno solo). identificador opcional: si falta, sale del nombre.
function gt_gimnasio_crear(PDO $pdo, $adminId, array $in) {
    $nombre    = trim((string) ($in['nombre'] ?? ''));
    $direccion = trim((string) ($in['direccion'] ?? ''));
    $telefono  = trim((string) ($in['telefono'] ?? ''));
    $mail      = trim((string) ($in['mail'] ?? ''));
    $ident     = isset($in['identificador']) && trim((string) $in['identificador']) !== ''
        ? gt_normalizar_identificador($in['identificador']) : null;

    if ($nombre === '' || gt_largo($nombre) > 100) {
        return gt_res_error(400, 'Ingresá el nombre del gimnasio (hasta 100 caracteres).');
    }
    if (gt_largo($direccion) > 150 || gt_largo($telefono) > 30) {
        return gt_res_error(400, 'La dirección o el teléfono son demasiado largos.');
    }
    if ($mail !== '' && (gt_largo($mail) > 60 || !filter_var($mail, FILTER_VALIDATE_EMAIL))) {
        return gt_res_error(400, 'El mail del gimnasio no es válido.');
    }
    if ($ident !== null && !gt_identificador_valido($ident)) {
        return gt_res_error(400, 'El identificador debe tener entre 3 y 60 caracteres: letras minúsculas, números y guiones.');
    }

    try {
        return gt_tx($pdo, function () use ($pdo, $adminId, $nombre, $direccion, $telefono, $mail, $ident) {
            $st = $pdo->prepare("SELECT es_admin FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
            $st->execute([(int) $adminId]);
            $u = $st->fetch();
            if (!$u || ($u['es_admin'] ?? 'N') !== 'S') {
                return gt_res_error(403, 'Acceso denegado: se requieren permisos de administrador.');
            }

            $st = $pdo->prepare("SELECT ID_Gimnasio FROM Gimnasio WHERE ID_Usuario_Dueno = ? LIMIT 1");
            $st->execute([(int) $adminId]);
            if ($st->fetch()) {
                return gt_res_error(409, 'Ya tenés un gimnasio. Cada administrador puede tener uno solo.');
            }

            if ($ident === null) {
                $ident = gt_identificador_libre($pdo, gt_slug($nombre));
                if ($ident === null) {
                    return gt_res_error(409, 'No pudimos generar un identificador libre. Elegí uno propio.');
                }
            }

            $pdo->prepare("
                INSERT INTO Gimnasio (nombre, identificador, direccion, telefono, mail, fecha_alta, activo, ID_Usuario_Dueno)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'S', ?)
            ")->execute([
                $nombre, $ident,
                $direccion !== '' ? $direccion : null,
                $telefono !== '' ? $telefono : null,
                $mail !== '' ? $mail : null,
                (int) $adminId,
            ]);

            return gt_res_ok('Gimnasio creado.', [
                'ID_Gimnasio'   => (int) $pdo->lastInsertId(),
                'nombre'        => $nombre,
                'identificador' => $ident,
            ], 201);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // clave unica: identificador (o duenio) ya en uso
            return gt_res_error(409, 'Ese identificador ya está en uso. Probá con otro.');
        }
        throw $e;
    }
}

// POST: el admin actualiza los datos de SU gimnasio (nombre, direccion, telefono, mail, identificador).
function gt_gimnasio_actualizar(PDO $pdo, $adminId, array $in) {
    $ctx = gt_lib_admin_con_gimnasio($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx['error'];
    }
    $idGym = $ctx['gimnasio'];

    // Obtener datos actuales del gimnasio
    $st = $pdo->prepare("SELECT * FROM Gimnasio WHERE ID_Gimnasio = ? LIMIT 1");
    $st->execute([$idGym]);
    $gymActual = $st->fetch();
    if (!$gymActual) {
        return gt_res_error(404, 'Gimnasio no encontrado.');
    }

    $nombre    = array_key_exists('nombre', $in) ? trim((string) $in['nombre']) : $gymActual['nombre'];
    $direccion = array_key_exists('direccion', $in) ? trim((string) $in['direccion']) : ($gymActual['direccion'] ?? '');
    $telefono  = array_key_exists('telefono', $in) ? trim((string) $in['telefono']) : ($gymActual['telefono'] ?? '');
    $mail      = array_key_exists('mail', $in) ? trim((string) $in['mail']) : ($gymActual['mail'] ?? '');
    
    if (array_key_exists('identificador', $in) && trim((string) $in['identificador']) !== '') {
        $ident = gt_normalizar_identificador($in['identificador']);
    } else {
        $ident = $gymActual['identificador'];
    }

    if ($nombre === '' || gt_largo($nombre) > 100) {
        return gt_res_error(400, 'Ingresá el nombre del gimnasio (hasta 100 caracteres).');
    }
    if (gt_largo($direccion) > 150 || gt_largo($telefono) > 30) {
        return gt_res_error(400, 'La dirección o el teléfono son demasiado largos.');
    }
    if ($mail !== '' && (gt_largo($mail) > 60 || !filter_var($mail, FILTER_VALIDATE_EMAIL))) {
        return gt_res_error(400, 'El mail del gimnasio no es válido.');
    }
    if ($ident === '' || !gt_identificador_valido($ident)) {
        return gt_res_error(400, 'El identificador debe tener entre 3 y 60 caracteres: letras minúsculas, números y guiones.');
    }

    // Verificar si el identificador cambió y si ya está en uso por otro gimnasio
    if ($ident !== $gymActual['identificador']) {
        $stCheck = $pdo->prepare("SELECT 1 FROM Gimnasio WHERE identificador = ? AND ID_Gimnasio != ? LIMIT 1");
        $stCheck->execute([$ident, $idGym]);
        if ($stCheck->fetch()) {
            return gt_res_error(409, 'Ese identificador ya está en uso por otro gimnasio. Elegí otro.');
        }
    }

    try {
        return gt_tx($pdo, function () use ($pdo, $idGym, $nombre, $direccion, $telefono, $mail, $ident) {
            $pdo->prepare("
                UPDATE Gimnasio
                SET nombre = ?, identificador = ?, direccion = ?, telefono = ?, mail = ?
                WHERE ID_Gimnasio = ?
            ")->execute([
                $nombre,
                $ident,
                $direccion !== '' ? $direccion : null,
                $telefono !== '' ? $telefono : null,
                $mail !== '' ? $mail : null,
                $idGym
            ]);

            return gt_res_ok('Gimnasio actualizado correctamente.', [
                'ID_Gimnasio'   => $idGym,
                'nombre'        => $nombre,
                'identificador' => $ident,
                'direccion'     => $direccion,
                'telefono'      => $telefono,
                'mail'          => $mail
            ]);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return gt_res_error(409, 'Ese identificador ya está en uso por otro gimnasio.');
        }
        throw $e;
    }
}

// GET: datos del gimnasio del admin (o data = null si todavia no lo creo), con contadores.
function gt_gimnasio_mio(PDO $pdo, $adminId) {
    $ctx = gt_lib_admin($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx['error'];
    }
    if ($ctx['gimnasio'] === null) {
        return gt_res(200, ['ok' => true, 'data' => null, 'codigo' => 'ADMIN_SIN_GIMNASIO']);
    }

    $st = $pdo->prepare("
        SELECT ID_Gimnasio, nombre, identificador, direccion, telefono, mail, fecha_alta
        FROM Gimnasio WHERE ID_Gimnasio = ? LIMIT 1
    ");
    $st->execute([$ctx['gimnasio']]);
    $g = $st->fetch();

    $st = $pdo->prepare("SELECT COUNT(*) AS n FROM Usuario WHERE ID_Gimnasio = ?");
    $st->execute([$ctx['gimnasio']]);
    $g['socios'] = (int) $st->fetch()['n'];

    $st = $pdo->prepare("SELECT COUNT(*) AS n FROM Solicitud WHERE ID_Gimnasio = ? AND estado = 'pendiente'");
    $st->execute([$ctx['gimnasio']]);
    $g['solicitudes_pendientes'] = (int) $st->fetch()['n'];

    return gt_res_ok('OK', $g);
}

// GET (cualquier usuario logueado): busca UN gimnasio por identificador exacto. No hay listado
// ni busqueda parcial: el identificador solo ubica la fila; unirse requiere aprobacion del admin.
function gt_gimnasio_buscar(PDO $pdo, $identificador) {
    if (!gt_throttle('buscar_gimnasio', GT_MAX_BUSQUEDAS_GYM)) {
        return gt_res_error(429, 'Demasiados intentos. Esperá un minuto y volvé a probar.');
    }
    $ident = gt_normalizar_identificador($identificador);
    if (!gt_identificador_valido($ident)) {
        return gt_res_error(400, 'Ingresá un identificador válido (letras, números y guiones).');
    }
    $st = $pdo->prepare("SELECT nombre, identificador FROM Gimnasio WHERE identificador = ? AND activo = 'S' LIMIT 1");
    $st->execute([$ident]);
    $g = $st->fetch();
    if (!$g) {
        return gt_res_error(404, 'No encontramos un gimnasio con ese identificador.');
    }
    return gt_res_ok('OK', $g);
}

// ---------------------------------------------------------------- solicitudes (socio)

// POST: un socio pide unirse al gimnasio con ese identificador.
function gt_solicitud_crear(PDO $pdo, $userId, $identificador) {
    $ident = gt_normalizar_identificador($identificador);
    if (!gt_identificador_valido($ident)) {
        return gt_res_error(400, 'Ingresá un identificador válido (letras, números y guiones).');
    }

    return gt_tx($pdo, function () use ($pdo, $userId, $ident) {
        // El lock sobre la fila del usuario serializa sus pedidos: no puede quedar con dos pendientes.
        $st = $pdo->prepare("SELECT es_admin, ID_Gimnasio FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
        $st->execute([(int) $userId]);
        $u = $st->fetch();
        if (!$u) {
            return gt_res_error(401, 'No autorizado. Iniciá sesión primero.');
        }
        if (($u['es_admin'] ?? 'N') === 'S') {
            return gt_res_error(403, 'Los administradores no pueden unirse a un gimnasio como socios.');
        }
        if ($u['ID_Gimnasio'] !== null) {
            return gt_res_error(409, 'Ya pertenecés a un gimnasio. Salí de él antes de pedir unirte a otro.');
        }

        $st = $pdo->prepare("SELECT ID_Gimnasio, nombre FROM Gimnasio WHERE identificador = ? AND activo = 'S' LIMIT 1");
        $st->execute([$ident]);
        $g = $st->fetch();
        if (!$g) {
            return gt_res_error(404, 'No encontramos un gimnasio con ese identificador.');
        }

        $st = $pdo->prepare("SELECT 1 FROM Solicitud WHERE ID_Usuario = ? AND estado = 'pendiente' LIMIT 1");
        $st->execute([(int) $userId]);
        if ($st->fetch()) {
            return gt_res_error(409, 'Ya tenés una solicitud pendiente. Cancelala o esperá la respuesta del gimnasio.');
        }

        $dias = (int) GT_COOLDOWN_DIAS;
        $st = $pdo->prepare("
            SELECT GREATEST(1, CEIL($dias - TIMESTAMPDIFF(SECOND, fecha_resolucion, NOW()) / 86400)) AS dias
            FROM Solicitud
            WHERE ID_Usuario = ? AND ID_Gimnasio = ? AND estado = 'rechazada'
              AND fecha_resolucion IS NOT NULL AND fecha_resolucion > (NOW() - INTERVAL $dias DAY)
            ORDER BY fecha_resolucion DESC LIMIT 1
        ");
        $st->execute([(int) $userId, (int) $g['ID_Gimnasio']]);
        $rechazo = $st->fetch();
        if ($rechazo) {
            $d = (int) $rechazo['dias'];
            return gt_res_error(429, 'Este gimnasio rechazó tu solicitud hace poco. Podés volver a intentarlo en ' . $d . ($d === 1 ? ' día.' : ' días.'), 'COOLDOWN');
        }

        $pdo->prepare("INSERT INTO Solicitud (ID_Usuario, ID_Gimnasio, estado, fecha_solicitud) VALUES (?, ?, 'pendiente', NOW())")
            ->execute([(int) $userId, (int) $g['ID_Gimnasio']]);

        return gt_res_ok('Solicitud enviada. El gimnasio la va a revisar.', [
            'ID_Solicitud' => (int) $pdo->lastInsertId(),
            'estado'       => 'pendiente',
            'gimnasio'     => $g['nombre'],
        ], 201);
    });
}

// GET: gimnasio actual del socio y su ultima solicitud (con dias para reintentar si fue rechazada).
function gt_solicitud_mia(PDO $pdo, $userId) {
    $st = $pdo->prepare("
        SELECT g.nombre, g.identificador
        FROM Usuario u JOIN Gimnasio g ON g.ID_Gimnasio = u.ID_Gimnasio
        WHERE u.ID_Usuario = ? LIMIT 1
    ");
    $st->execute([(int) $userId]);
    $gimnasio = $st->fetch() ?: null;

    $dias = (int) GT_COOLDOWN_DIAS;
    $st = $pdo->prepare("
        SELECT s.ID_Solicitud, s.estado, s.fecha_solicitud, s.fecha_resolucion, g.nombre AS gimnasio,
               CASE WHEN s.estado = 'rechazada' AND s.fecha_resolucion > (NOW() - INTERVAL $dias DAY)
                    THEN GREATEST(1, CEIL($dias - TIMESTAMPDIFF(SECOND, s.fecha_resolucion, NOW()) / 86400))
                    ELSE NULL END AS dias_para_reintentar
        FROM Solicitud s JOIN Gimnasio g ON g.ID_Gimnasio = s.ID_Gimnasio
        WHERE s.ID_Usuario = ?
        ORDER BY s.ID_Solicitud DESC LIMIT 1
    ");
    $st->execute([(int) $userId]);
    $sol = $st->fetch() ?: null;

    return gt_res_ok('OK', ['gimnasio' => $gimnasio, 'solicitud' => $sol]);
}

// POST: el socio cancela su solicitud pendiente.
function gt_solicitud_cancelar(PDO $pdo, $userId) {
    return gt_tx($pdo, function () use ($pdo, $userId) {
        $pdo->prepare("SELECT ID_Usuario FROM Usuario WHERE ID_Usuario = ? FOR UPDATE")->execute([(int) $userId]);
        $st = $pdo->prepare("SELECT ID_Solicitud FROM Solicitud WHERE ID_Usuario = ? AND estado = 'pendiente' LIMIT 1 FOR UPDATE");
        $st->execute([(int) $userId]);
        $s = $st->fetch();
        if (!$s) {
            return gt_res_error(404, 'No tenés ninguna solicitud pendiente.');
        }
        $pdo->prepare("UPDATE Solicitud SET estado = 'cancelada', fecha_resolucion = NOW() WHERE ID_Solicitud = ?")
            ->execute([(int) $s['ID_Solicitud']]);
        return gt_res_ok('Solicitud cancelada.');
    });
}

// POST: el socio sale de su gimnasio (queda independiente y puede pedir otro).
function gt_usuario_salir(PDO $pdo, $userId) {
    $gymSalido = null;
    $res = gt_tx($pdo, function () use ($pdo, $userId, &$gymSalido) {
        $st = $pdo->prepare("SELECT ID_Gimnasio FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
        $st->execute([(int) $userId]);
        $u = $st->fetch();
        if (!$u || $u['ID_Gimnasio'] === null) {
            return gt_res_error(409, 'No pertenecés a ningún gimnasio.');
        }
        $gymSalido = (int) $u['ID_Gimnasio'];
        $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = NULL WHERE ID_Usuario = ?")->execute([(int) $userId]);
        gt_registrar_movimiento($pdo, $userId, $gymSalido, 'baja', 'socio');
        return gt_res_ok('Saliste del gimnasio.');
    });
    if ($gymSalido !== null && ($res['body']['ok'] ?? false)) {
        gt_notificar_cambio_gimnasio($pdo, $userId, $gymSalido, 'baja', 'socio');
    }
    return $res;
}

// ---------------------------------------------------------------- solicitudes (admin)

// GET: solicitudes pendientes de SU gimnasio. Solo lo necesario para decidir (sin DNI ni telefono).
function gt_solicitudes_pendientes(PDO $pdo, $adminId) {
    $ctx = gt_lib_admin_con_gimnasio($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx['error'];
    }
    $st = $pdo->prepare("
        SELECT s.ID_Solicitud, s.fecha_solicitud, u.ID_Usuario, u.nombre, u.apellido, u.mail
        FROM Solicitud s JOIN Usuario u ON u.ID_Usuario = s.ID_Usuario
        WHERE s.ID_Gimnasio = ? AND s.estado = 'pendiente'
        ORDER BY s.fecha_solicitud ASC, s.ID_Solicitud ASC
    ");
    $st->execute([$ctx['gimnasio']]);
    return gt_res_ok('OK', $st->fetchAll());
}

// POST: el admin aprueba o rechaza una solicitud de SU gimnasio.
function gt_solicitud_resolver(PDO $pdo, $adminId, $idSolicitud, $accion) {
    $accion = strtolower(trim((string) $accion));
    $idSolicitud = (int) $idSolicitud;
    if ($idSolicitud <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
        return gt_res_error(400, 'Faltan datos: id_solicitud y accion ("aprobar" o "rechazar").');
    }
    $ctx = gt_lib_admin_con_gimnasio($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx['error'];
    }
    $miGym = $ctx['gimnasio'];

    $notificarAlta = null;
    $res = gt_tx($pdo, function () use ($pdo, $miGym, $idSolicitud, $accion, &$notificarAlta) {
        // Lectura sin bloqueo para saber de que usuario es; "no existe" y "es de otro gimnasio" dan lo mismo.
        $st = $pdo->prepare("SELECT ID_Usuario FROM Solicitud WHERE ID_Solicitud = ? AND ID_Gimnasio = ? LIMIT 1");
        $st->execute([$idSolicitud, $miGym]);
        $s = $st->fetch();
        if (!$s) {
            return gt_res_error(404, 'La solicitud no existe.');
        }
        $uid = (int) $s['ID_Usuario'];

        // Orden de locks: Usuario -> Solicitud. Despues se vuelve a verificar el estado.
        $st = $pdo->prepare("SELECT es_admin, ID_Gimnasio FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
        $st->execute([$uid]);
        $u = $st->fetch();

        $st = $pdo->prepare("SELECT estado FROM Solicitud WHERE ID_Solicitud = ? AND ID_Gimnasio = ? FOR UPDATE");
        $st->execute([$idSolicitud, $miGym]);
        $actual = $st->fetch();
        if (!$actual) {
            return gt_res_error(404, 'La solicitud no existe.');
        }
        if ($actual['estado'] !== 'pendiente') {
            return gt_res_error(409, 'Esa solicitud ya fue resuelta.');
        }

        $cerrar = $pdo->prepare("UPDATE Solicitud SET estado = ?, fecha_resolucion = NOW() WHERE ID_Solicitud = ?");

        if ($accion === 'rechazar') {
            $cerrar->execute(['rechazada', $idSolicitud]);
            return gt_res_ok('Solicitud rechazada.');
        }

        // APROBAR
        if (!$u || ($u['es_admin'] ?? 'N') === 'S') {
            $cerrar->execute(['cancelada', $idSolicitud]);
            return gt_res_error(409, 'Ese usuario ya no puede unirse como socio. La solicitud se canceló.');
        }
        $gymUsuario = $u['ID_Gimnasio'] !== null ? (int) $u['ID_Gimnasio'] : null;
        if ($gymUsuario !== null && $gymUsuario !== $miGym) {
            $cerrar->execute(['cancelada', $idSolicitud]);
            return gt_res_error(409, 'El usuario ya pertenece a otro gimnasio. La solicitud se canceló.');
        }
        if ($gymUsuario === null) {
            $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = ? WHERE ID_Usuario = ?")->execute([$miGym, $uid]);
            gt_registrar_movimiento($pdo, $uid, $miGym, 'alta', 'solicitud');
            $notificarAlta = ['uid' => $uid, 'gym' => $miGym];
        }
        $cerrar->execute(['aceptada', $idSolicitud]);
        return gt_res_ok('Solicitud aprobada. El usuario ya es socio de tu gimnasio.');
    });

    if ($notificarAlta !== null && ($res['body']['ok'] ?? false)) {
        gt_notificar_cambio_gimnasio($pdo, $notificarAlta['uid'], $notificarAlta['gym'], 'alta', 'solicitud');
    }
    return $res;
}

// ---------------------------------------------------------------- herramienta directa del admin

// GET: el admin busca a UNA persona por mail exacto para sumarla directamente (asignar_gimnasio).
// Solo expone datos personales de usuarios libres o ya socios de su gimnasio; si pertenece a
// otro gimnasio solo informa eso. Las cuentas de administrador no se revelan.
function gt_usuario_buscar(PDO $pdo, $adminId, $mail) {
    $ctx = gt_lib_admin_con_gimnasio($pdo, $adminId);
    if (isset($ctx['error'])) {
        return $ctx['error'];
    }
    if (!gt_throttle('buscar_usuario', GT_MAX_BUSQUEDAS_USUARIO)) {
        return gt_res_error(429, 'Demasiados intentos. Esperá un minuto y volvé a probar.');
    }
    $mail = trim((string) $mail);
    if ($mail === '' || strlen($mail) > 60 || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        return gt_res_error(400, 'Ingresá un mail válido.');
    }

    $st = $pdo->prepare("SELECT ID_Usuario, nombre, apellido, mail, ID_Gimnasio, es_admin FROM Usuario WHERE mail = ? LIMIT 1");
    $st->execute([$mail]);
    $u = $st->fetch();
    if (!$u || ($u['es_admin'] ?? 'N') === 'S') {
        return gt_res_error(404, 'No encontramos un usuario con ese mail.');
    }

    $gym = $u['ID_Gimnasio'] !== null ? (int) $u['ID_Gimnasio'] : null;
    if ($gym !== null && $gym !== $ctx['gimnasio']) {
        return gt_res_ok('OK', ['estado' => 'otro_gimnasio']);
    }
    return gt_res_ok('OK', [
        'estado'     => $gym === null ? 'libre' : 'socio',
        'ID_Usuario' => (int) $u['ID_Usuario'],
        'nombre'     => $u['nombre'],
        'apellido'   => $u['apellido'],
        'mail'       => $u['mail'],
    ]);
}
