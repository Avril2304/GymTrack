<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Gimnasios y solicitudes de ingreso (capa MVC).
 *
 * Las reglas de negocio viven en public_html/api/gimnasio_lib.php y son las mismas que usan los
 * endpoints PHP directos: una sola implementacion, dos puertas de entrada. Este controlador solo
 * identifica a quien llama (siempre por la sesion del servidor), exige el metodo HTTP correcto
 * y traduce la respuesta de la libreria.
 */
class SolicitudController
{
    // ---------------------------------------------------------------- gimnasio

    public function crearGimnasio(Request $request)
    {
        return $this->ejecutar('POST', function ($pdo, $uid) use ($request) {
            return \gt_gimnasio_crear($pdo, $uid, [
                'nombre'        => $request->input('nombre'),
                'direccion'     => $request->input('direccion'),
                'telefono'      => $request->input('telefono'),
                'mail'          => $request->input('mail'),
                'identificador' => $request->input('identificador'),
            ]);
        });
    }

    public function actualizarGimnasio(Request $request)
    {
        return $this->ejecutar('POST', function ($pdo, $uid) use ($request) {
            $in = [];
            foreach (['nombre', 'direccion', 'telefono', 'mail', 'identificador'] as $campo) {
                if ($request->has($campo)) {
                    $in[$campo] = $request->input($campo);
                }
            }
            return \gt_gimnasio_actualizar($pdo, $uid, $in);
        });
    }

    public function miGimnasio()
    {
        return $this->ejecutar('GET', function ($pdo, $uid) {
            return \gt_gimnasio_mio($pdo, $uid);
        });
    }

    public function buscarGimnasio(Request $request)
    {
        return $this->ejecutar('GET', function ($pdo, $uid) use ($request) {
            return \gt_gimnasio_buscar($pdo, $request->input('identificador', ''));
        });
    }

    public function salirGimnasio()
    {
        return $this->ejecutar('POST', function ($pdo, $uid) {
            return \gt_usuario_salir($pdo, $uid);
        });
    }

    // ---------------------------------------------------------------- solicitudes (socio)

    public function crear(Request $request)
    {
        return $this->ejecutar('POST', function ($pdo, $uid) use ($request) {
            return \gt_solicitud_crear($pdo, $uid, $request->input('identificador', ''));
        });
    }

    public function mia()
    {
        return $this->ejecutar('GET', function ($pdo, $uid) {
            return \gt_solicitud_mia($pdo, $uid);
        });
    }

    public function cancelar()
    {
        return $this->ejecutar('POST', function ($pdo, $uid) {
            return \gt_solicitud_cancelar($pdo, $uid);
        });
    }

    // ---------------------------------------------------------------- solicitudes (admin)

    public function pendientes()
    {
        return $this->ejecutar('GET', function ($pdo, $uid) {
            return \gt_solicitudes_pendientes($pdo, $uid);
        });
    }

    public function resolver(Request $request)
    {
        return $this->ejecutar('POST', function ($pdo, $uid) use ($request) {
            return \gt_solicitud_resolver($pdo, $uid, $request->input('id_solicitud', 0), $request->input('accion', ''));
        });
    }

    public function buscarUsuario(Request $request)
    {
        return $this->ejecutar('GET', function ($pdo, $uid) use ($request) {
            return \gt_usuario_buscar($pdo, $uid, $request->input('mail', ''));
        });
    }

    // ---------------------------------------------------------------- comun

    /**
     * Valida metodo y sesion, y ejecuta $fn($pdo, $userId), que devuelve ['status' => , 'body' => ].
     */
    private function ejecutar($metodo, callable $fn)
    {
        $ruta = dirname(__DIR__, 4) . '/public_html/api/gimnasio_lib.php';
        if (!file_exists($ruta)) {
            return response()->json(['ok' => false, 'msg' => 'No se encontró public_html/api/gimnasio_lib.php.'], 500);
        }
        require_once $ruta;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $metodo) {
            return response()->json(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        \gt_iniciar_sesion();
        $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($uid <= 0) {
            return response()->json(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión primero.'], 401);
        }

        try {
            $res = $fn(Capsule::getPdo(), $uid);
        } catch (\Throwable $e) {
            error_log('SolicitudController: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'No se pudo completar la operación.'], 500);
        }

        return response()->json($res['body'], $res['status']);
    }
}
