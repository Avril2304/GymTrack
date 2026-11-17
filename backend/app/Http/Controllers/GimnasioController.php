<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Gimnasio;
use Illuminate\Http\Request;

class GimnasioController
{
    /**
     * POST /admin/asignar-gimnasio
     * Body: { user_id, id_gimnasio }  (id_gimnasio = null para desvincular)
     *
     * Herramienta directa del administrador: sumar un socio libre a SU gimnasio, o sacar a un
     * socio de SU gimnasio. La identidad del admin sale SOLO de la sesión del servidor;
     * cualquier admin_id que mande el cliente se ignora.
     */
    public function asignarGimnasio(Request $request)
    {
        // Modifica datos: solo POST (un GET se podría disparar desde un link ajeno con la cookie de sesión).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return response()->json(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Autenticación y permisos primero (401/403), antes de revelar si algo existe (404).
        $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($adminId <= 0) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión primero.'], 401);
        }

        $admin = Usuario::find($adminId);
        if (!$admin || ($admin->es_admin ?? 'N') !== 'S') {
            return response()->json(['ok' => false, 'msg' => 'Acceso denegado: se requieren permisos de administrador.'], 403);
        }

        $gym = Gimnasio::where('ID_Usuario_Dueno', $adminId)->where('activo', 'S')->first();
        if (!$gym) {
            return response()->json([
                'ok' => false,
                'msg' => 'Tu cuenta de administrador todavía no tiene un gimnasio asignado.',
                'codigo' => 'ADMIN_SIN_GIMNASIO'
            ], 403);
        }
        $miGym = (int) $gym->ID_Gimnasio;

        // 2. Validación de la entrada
        $userId     = $request->input('user_id') ? (int) $request->input('user_id') : 0;
        $rawGym     = $request->input('id_gimnasio');
        $destino    = ($rawGym !== null && $rawGym !== '') ? (int) $rawGym : null;
        // Distinguir "no vino el campo" de "vino null" (null = desvincular)
        $campoVino  = array_key_exists('id_gimnasio', $request->only('id_gimnasio'));

        if ($userId <= 0 || !$campoVino) {
            return response()->json(['ok' => false, 'msg' => 'Faltan campos obligatorios: user_id e id_gimnasio.'], 400);
        }

        // Un admin solo puede conectar socios a SU gimnasio.
        if ($destino !== null && $destino !== $miGym) {
            return response()->json(['ok' => false, 'msg' => 'Solo podés asignar socios a tu propio gimnasio.'], 403);
        }

        // 3. Estado del usuario destino, leído con bloqueo para decidir sin carreras
        $pdo = \Illuminate\Database\Capsule\Manager::getPdo();

        // Registro de altas y bajas (Movimiento_Socio), compartido con la capa api/
        $rutaMovimientos = dirname(__DIR__, 4) . '/public_html/api/movimientos.php';
        if (!file_exists($rutaMovimientos)) {
            return response()->json(['ok' => false, 'msg' => 'No se encontró public_html/api/movimientos.php.'], 500);
        }
        require_once $rutaMovimientos;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT ID_Gimnasio, es_admin FROM Usuario WHERE ID_Usuario = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
            $actual = ($fila && $fila['ID_Gimnasio'] !== null) ? (int) $fila['ID_Gimnasio'] : null;

            if ($destino !== null) {
                // CONECTAR: el socio tiene que existir y estar libre (o ya ser de mi gimnasio).
                if (!$fila) {
                    $pdo->rollBack();
                    return response()->json(['ok' => false, 'msg' => 'El usuario indicado no existe.'], 404);
                }
                if (($fila['es_admin'] ?? 'N') === 'S') {
                    $pdo->rollBack();
                    return response()->json(['ok' => false, 'msg' => 'No se puede asignar a un administrador como socio.'], 409);
                }
                if ($actual !== null && $actual !== $miGym) {
                    $pdo->rollBack();
                    return response()->json(['ok' => false, 'msg' => 'Ese usuario ya pertenece a otro gimnasio.'], 409);
                }
                if ($actual === null) {
                    $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = ? WHERE ID_Usuario = ?")->execute([$miGym, $userId]);
                    \gt_registrar_movimiento($pdo, $userId, $miGym, 'alta', 'admin');
                    // Si tenia una solicitud pendiente (a este u otro gimnasio), queda sin efecto.
                    $pdo->prepare("UPDATE Solicitud SET estado = 'cancelada', fecha_resolucion = NOW() WHERE ID_Usuario = ? AND estado = 'pendiente'")->execute([$userId]);
                }
            } else {
                // DESVINCULAR: solo socios de mi gimnasio. Mismo error si no existe o es de otro gimnasio.
                if (!$fila || $actual !== $miGym) {
                    $pdo->rollBack();
                    return response()->json(['ok' => false, 'msg' => 'Ese usuario no pertenece a tu gimnasio.'], 404);
                }
                $pdo->prepare("UPDATE Usuario SET ID_Gimnasio = NULL WHERE ID_Usuario = ?")->execute([$userId]);
                \gt_registrar_movimiento($pdo, $userId, $miGym, 'baja', 'admin');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('GimnasioController::asignarGimnasio: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'No se pudo actualizar el gimnasio del usuario.'], 500);
        }

        return response()->json([
            'ok'  => true,
            'msg' => $destino !== null ? 'Usuario conectado al gimnasio exitosamente.' : 'Usuario desvinculado del gimnasio exitosamente.',
            'data' => [
                'ID_Usuario'  => $userId,
                'ID_Gimnasio' => $destino
            ]
        ]);
    }

    /**
     * GET /admin/gimnasios
     * Datos del gimnasio del administrador logueado. Ya no lista todos los gimnasios:
     * eso expondría a cualquier admin los datos de contacto de los demás.
     */
    public function listar()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($adminId <= 0) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión primero.'], 401);
        }

        $admin = Usuario::find($adminId);
        if (!$admin || ($admin->es_admin ?? 'N') !== 'S') {
            return response()->json(['ok' => false, 'msg' => 'Acceso denegado: se requieren permisos de administrador.'], 403);
        }

        try {
            $pdo = \Illuminate\Database\Capsule\Manager::getPdo();
            $stmt = $pdo->prepare("
                SELECT g.ID_Gimnasio, g.nombre, g.direccion, g.telefono, g.mail, g.activo
                FROM Gimnasio g
                WHERE g.ID_Usuario_Dueno = ? AND g.activo = 'S'
                ORDER BY g.nombre ASC
            ");
            $stmt->execute([$adminId]);

            return response()->json(['ok' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            error_log('GimnasioController::listar: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Error al obtener el gimnasio.'], 500);
        }
    }
}
