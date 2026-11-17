<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Rutina;
use App\Models\Historial;
use App\Models\Ejercicio;
use App\Models\Clase;
use App\Models\Gimnasio;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;

class AdminController
{
    public function usuarios(Request $request = null)
    {
        try {
            // La identidad sale SOLO de la sesión del servidor; el cliente no elige gimnasio ni admin.
            $ctx = $this->adminConGimnasio();
            if (isset($ctx['error'])) {
                return $ctx['error'];
            }

            // El admin solo puede ver los socios de su propio gimnasio, sin excepciones.
            $query = Usuario::with(['clase', 'gimnasio'])->where('ID_Gimnasio', $ctx['idGimnasio']);

            $usuarios = $query->get()->map(function ($u) {
                return [
                    'ID_Usuario'      => $u->ID_Usuario,
                    'nombre'          => $u->nombre ?? '',
                    'apellido'        => $u->apellido ?? '',
                    'nombre_completo' => trim(($u->nombre ?? '') . ' ' . ($u->apellido ?? '')),
                    'mail'            => $u->mail ?? '',
                    'email'           => $u->mail ?? '',
                    'telefono'        => $u->telefono ?? '',
                    'DNI'             => $u->DNI ?? '',
                    'peso'            => $u->peso ?? 0,
                    'altura'          => $u->altura ?? 0,
                    'fecha_nacimiento' => $u->fecha_nacimiento ?? '',
                    'fecha_alta'      => $u->fecha_alta ?? '',
                    'sexo'            => $u->sexo ?? 'M',
                    'foto'            => $u->foto ?? 'default.jpg',
                    'es_admin'        => $u->es_admin ?? 'N',
                    'ID_Gimnasio'     => $u->ID_Gimnasio ?? null,
                    'nombre_clase'    => $u->clase   ? $u->clase->nombre   : 'Sin asignar',
                    'nombre_gimnasio' => $u->gimnasio ? $u->gimnasio->nombre : null,
                ];
            });

            return response()->json(['ok' => true, 'data' => $usuarios]);
        } catch (\Throwable $e) {
            error_log('AdminController::usuarios: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Error al obtener usuarios.'], 500);
        }
    }

    /**
     * Identifica al admin SOLO por la sesión del servidor y devuelve el gimnasio que posee
     * (Gimnasio.ID_Usuario_Dueno). Devuelve ['idGimnasio' => int] o ['error' => respuesta lista para retornar].
     * Un admin sin gimnasio no ve datos de nadie (nunca "todos").
     */
    private function adminConGimnasio()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($adminId <= 0) {
            return ['error' => response()->json(['ok' => false, 'msg' => 'No autorizado. Iniciá sesión primero.'], 401)];
        }

        $admin = Usuario::find($adminId);
        if (!$admin || ($admin->es_admin ?? 'N') !== 'S') {
            return ['error' => response()->json(['ok' => false, 'msg' => 'Acceso denegado: se requieren permisos de administrador.'], 403)];
        }

        $gym = Gimnasio::where('ID_Usuario_Dueno', $adminId)->where('activo', 'S')->first();
        if (!$gym) {
            return ['error' => response()->json([
                'ok' => false,
                'msg' => 'Tu cuenta de administrador todavía no tiene un gimnasio asignado.',
                'codigo' => 'ADMIN_SIN_GIMNASIO'
            ], 403)];
        }

        return ['idGimnasio' => (int) $gym->ID_Gimnasio];
    }

    public function dashboard(Request $request = null)
    {
        try {
            // Solo el admin logueado (por sesión), y solo con las métricas de SU gimnasio.
            $ctx = $this->adminConGimnasio();
            if (isset($ctx['error'])) {
                return $ctx['error'];
            }
            $idGimnasio = $ctx['idGimnasio'];

            $pdo = Capsule::getPdo();

            // Fragmentos de filtro por gimnasio, reutilizados en cada consulta.
            $filtroA = $idGimnasio !== null ? "AND a.ID_Gimnasio = :g" : "";       // alias Asistencia
            $filtroU = $idGimnasio !== null ? "AND ID_Gimnasio = :g" : "";        // Usuario sin alias
            $filtroJoinU = $idGimnasio !== null ? "JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario AND u.ID_Gimnasio = :g" : "";
            $bindG = $idGimnasio !== null ? ['g' => $idGimnasio] : [];

            // 1. % de socios activos (asistieron en los últimos 7 días) — vía Asistencia
            $stmt = $pdo->prepare("
                SELECT ROUND(
                  (SELECT COUNT(DISTINCT a.ID_Usuario) FROM Asistencia a WHERE a.fecha >= CURDATE() - INTERVAL 7 DAY $filtroA)
                  / NULLIF((SELECT COUNT(*) FROM Usuario WHERE 1=1 $filtroU), 0) * 100
                , 1) AS valor
            ");
            $stmt->execute($bindG);
            $activos = $stmt->fetch(\PDO::FETCH_ASSOC)['valor'] ?? 0;

            // 2. Promedio de asistencias por día (últimos 30 días) — vía Asistencia
            $stmt = $pdo->prepare("
                SELECT ROUND(AVG(cantidad), 1) AS valor
                FROM (
                  SELECT a.fecha, COUNT(DISTINCT a.ID_Usuario) AS cantidad
                  FROM Asistencia a
                  WHERE a.fecha >= CURDATE() - INTERVAL 30 DAY $filtroA
                  GROUP BY a.fecha
                ) t
            ");
            $stmt->execute($bindG);
            $promedio_dia = $stmt->fetch(\PDO::FETCH_ASSOC)['valor'] ?? 0;

            // 3. % de socios nuevos vs. mes anterior
            $stmt = $pdo->prepare("
                SELECT
                  (SELECT COUNT(*) FROM Usuario WHERE fecha_alta >= CURDATE() - INTERVAL 30 DAY $filtroU) AS actual,
                  (SELECT COUNT(*) FROM Usuario WHERE fecha_alta >= CURDATE() - INTERVAL 60 DAY
                     AND fecha_alta < CURDATE() - INTERVAL 30 DAY $filtroU) AS anterior
            ");
            $stmt->execute($bindG);
            $nuevos = $stmt->fetch(\PDO::FETCH_ASSOC);

            $pct_nuevos = ($nuevos && $nuevos['anterior'] > 0)
                ? round((($nuevos['actual'] - $nuevos['anterior']) / $nuevos['anterior']) * 100, 1)
                : null;

            // 4. % de ejercicios del catálogo que se usaron alguna vez
            //    (numerador acotado al gym si corresponde; el catálogo de Ejercicio es global)
            $stmt = $pdo->prepare("
                SELECT ROUND(
                  (SELECT COUNT(DISTINCT h.ID_Ejercicio) FROM Historial h $filtroJoinU)
                  / NULLIF((SELECT COUNT(*) FROM Ejercicio), 0) * 100
                , 1) AS valor
            ");
            $stmt->execute($bindG);
            $ejercicios_usados = $stmt->fetch(\PDO::FETCH_ASSOC)['valor'] ?? 0;

            // ---------- GRÁFICOS ----------

            // 1. Tipos de actividad usados (vía Historial -> Ejercitacion -> Rutina -> Clase)
            $stmt = $pdo->prepare("
                SELECT c.nombre AS actividad, COUNT(DISTINCT h.ID_Historial) AS cantidad
                FROM Historial h
                $filtroJoinU
                JOIN Ejercitacion ej ON ej.ID_Ejercicio = h.ID_Ejercicio
                JOIN Rutina r ON r.ID_Rutina = ej.ID_Rutina
                JOIN Clase c ON c.ID_Clase = r.ID_Clase
                GROUP BY c.nombre
                ORDER BY cantidad DESC
            ");
            $stmt->execute($bindG);
            $actividades = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Asistencia por día de la semana — vía Asistencia (1=Domingo ... 7=Sábado)
            $stmt = $pdo->prepare("
                SELECT DAYOFWEEK(a.fecha) AS orden, COUNT(*) AS asistencias
                FROM Asistencia a
                WHERE 1=1 $filtroA
                GROUP BY orden
                ORDER BY orden
            ");
            $stmt->execute($bindG);
            $dias_semana = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Turno mañana vs. tarde — directo desde Asistencia.turno
            $stmt = $pdo->prepare("
                SELECT
                  CASE a.turno WHEN 'M' THEN 'Mañana' WHEN 'T' THEN 'Tarde' ELSE 'Sin dato' END AS turno,
                  COUNT(*) AS cantidad
                FROM Asistencia a
                WHERE a.turno IS NOT NULL $filtroA
                GROUP BY a.turno
            ");
            $stmt->execute($bindG);
            $turnos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 4. Top 10 ejercicios más usados
            $stmt = $pdo->prepare("
                SELECT e.nombre, COUNT(*) AS usos
                FROM Historial h
                $filtroJoinU
                JOIN Ejercicio e ON e.ID_Ejercicio = h.ID_Ejercicio
                GROUP BY e.ID_Ejercicio, e.nombre
                ORDER BY usos DESC
                LIMIT 10
            ");
            $stmt->execute($bindG);
            $top_ejercicios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Stats generales (acotados al gym cuando corresponde)
            $totalUsuarios = $idGimnasio !== null ? Usuario::where('ID_Gimnasio', $idGimnasio)->count() : Usuario::count();
            $totalRutinas = Rutina::count();
            $totalEjercicios = Ejercicio::count();
            $totalEntrenamientos = Historial::count();

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total_usuarios' => $totalUsuarios,
                    'total_rutinas' => $totalRutinas,
                    'total_ejercicios' => $totalEjercicios,
                    'total_entrenamientos' => $totalEntrenamientos
                ],
                'numeros' => [
                    'porcentaje_activos'           => (float) ($activos ?? 0),
                    'promedio_por_dia'             => (float) ($promedio_dia ?? 0),
                    'porcentaje_nuevos_vs_mes'     => $pct_nuevos,
                    'porcentaje_ejercicios_usados' => (float) ($ejercicios_usados ?? 0),
                ],
                'graficos' => [
                    'actividades'    => $actividades,
                    'dias_semana'    => $dias_semana,
                    'turnos'         => $turnos,
                    'top_ejercicios' => $top_ejercicios,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('AdminController::dashboard: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Error al cargar las estadísticas.'], 500);
        }
    }
}
