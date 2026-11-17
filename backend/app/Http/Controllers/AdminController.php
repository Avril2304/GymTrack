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
            $filtroJoinU = $idGimnasio !== null ? "JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario AND u.ID_Gimnasio = :g" : "";
            $bindG = $idGimnasio !== null ? ['g' => $idGimnasio] : [];

            // "Socios" = usuarios del gimnasio que no son administradores.
            // Las métricas de socios se filtran por el gimnasio del socio (Usuario.ID_Gimnasio);
            // las de entrenamientos, por el gimnasio donde se registró cada uno (Asistencia.ID_Gimnasio).
            $filtroSocio = "u.es_admin = 'N'" . ($idGimnasio !== null ? " AND u.ID_Gimnasio = :g" : "");

            // 1. Socios activos: los que entrenaron en los últimos 7 días, sobre el total de socios inscriptos
            $stmt = $pdo->prepare("
                SELECT
                  (SELECT COUNT(DISTINCT a.ID_Usuario)
                     FROM Asistencia a
                     JOIN Usuario u ON u.ID_Usuario = a.ID_Usuario
                    WHERE a.fecha >= CURDATE() - INTERVAL 7 DAY AND $filtroSocio) AS activos,
                  (SELECT COUNT(*) FROM Usuario u WHERE $filtroSocio) AS total
            ");
            $stmt->execute($bindG);
            $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
            $activosCant = (int) ($fila['activos'] ?? 0);
            $sociosTotal = (int) ($fila['total'] ?? 0);
            $activos = $sociosTotal > 0 ? round($activosCant / $sociosTotal * 100, 1) : 0;

            // 2 y 3. Socios nuevos y bajas: altas y bajas reales del gimnasio (tabla Movimiento_Socio)
            //        en los últimos 30 días y en los 30 días anteriores.
            $filtroM = $idGimnasio !== null ? "WHERE m.ID_Gimnasio = :g" : "";
            $stmt = $pdo->prepare("
                SELECT
                  SUM(m.tipo = 'alta' AND m.fecha >= CURDATE() - INTERVAL 30 DAY) AS altas_actual,
                  SUM(m.tipo = 'alta' AND m.fecha >= CURDATE() - INTERVAL 60 DAY
                      AND m.fecha < CURDATE() - INTERVAL 30 DAY) AS altas_anterior,
                  SUM(m.tipo = 'baja' AND m.fecha >= CURDATE() - INTERVAL 30 DAY) AS bajas_actual,
                  SUM(m.tipo = 'baja' AND m.fecha >= CURDATE() - INTERVAL 60 DAY
                      AND m.fecha < CURDATE() - INTERVAL 30 DAY) AS bajas_anterior
                FROM Movimiento_Socio m
                $filtroM
            ");
            $stmt->execute($bindG);
            $movimientos = $stmt->fetch(\PDO::FETCH_ASSOC);

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

            // 1. Socios por objetivo (la clase que eligió cada socio: Hipertrofia, Tonificación, etc.)
            $stmt = $pdo->prepare("
                SELECT COALESCE(c.nombre, 'Sin objetivo') AS objetivo, COUNT(*) AS cantidad
                FROM Usuario u
                LEFT JOIN Clase c ON c.ID_Clase = u.ID_Clase
                WHERE $filtroSocio
                GROUP BY objetivo
                ORDER BY cantidad DESC
            ");
            $stmt->execute($bindG);
            $objetivos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Entrenamientos por día de la semana — vía Asistencia (1=Domingo ... 7=Sábado)
            $stmt = $pdo->prepare("
                SELECT DAYOFWEEK(a.fecha) AS orden, COUNT(*) AS asistencias
                FROM Asistencia a
                WHERE 1=1 $filtroA
                GROUP BY orden
                ORDER BY orden
            ");
            $stmt->execute($bindG);
            $dias_semana = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Frecuencia semanal: cuántos días por semana entrena cada socio, en promedio,
            //    en las últimas 4 semanas (28 días, hoy incluido). Los socios sin entrenamientos
            //    también cuentan (LEFT JOIN), en el grupo "sin".
            //    Días en 28 días -> promedio semanal: 0 | 1-3 (<1) | 4-9 (1 a 2) | 10-17 (3 a 4) | 18+ (5 o más)
            $stmt = $pdo->prepare("
                SELECT
                  CASE
                    WHEN dias = 0  THEN 'sin'
                    WHEN dias < 4  THEN 'menos_1'
                    WHEN dias < 10 THEN '1_a_2'
                    WHEN dias < 18 THEN '3_a_4'
                    ELSE '5_o_mas'
                  END AS rango,
                  COUNT(*) AS cantidad
                FROM (
                  SELECT u.ID_Usuario, COUNT(DISTINCT a.fecha) AS dias
                  FROM Usuario u
                  LEFT JOIN Asistencia a
                         ON a.ID_Usuario = u.ID_Usuario
                        AND a.fecha >= CURDATE() - INTERVAL 27 DAY
                  WHERE $filtroSocio
                  GROUP BY u.ID_Usuario
                ) t
                GROUP BY rango
            ");
            $stmt->execute($bindG);
            // Orden fijo, con 0 en los grupos que no tengan socios
            $conteo = ['sin' => 0, 'menos_1' => 0, '1_a_2' => 0, '3_a_4' => 0, '5_o_mas' => 0];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
                $conteo[$fila['rango']] = (int) $fila['cantidad'];
            }
            $frecuencia_semanal = [];
            foreach ($conteo as $rango => $cantidad) {
                $frecuencia_semanal[] = ['rango' => $rango, 'cantidad' => $cantidad];
            }

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
            // Rutinas: las globales (catálogo) + las personales de socios del gym. Ejercicios: catálogo global.
            if ($idGimnasio !== null) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM Rutina r
                    LEFT JOIN Usuario u ON u.ID_Usuario = r.ID_Usuario
                    WHERE r.ID_Usuario IS NULL OR u.ID_Gimnasio = :g
                ");
                $stmt->execute($bindG);
                $totalRutinas = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Historial h $filtroJoinU");
                $stmt->execute($bindG);
                $totalEntrenamientos = (int) $stmt->fetchColumn();
            } else {
                $totalRutinas = Rutina::count();
                $totalEntrenamientos = Historial::count();
            }
            $totalEjercicios = Ejercicio::count();

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
                    'socios_activos'               => $activosCant,
                    'socios_total'                 => $sociosTotal,
                    'socios_nuevos'                => [
                        'actual'   => (int) ($movimientos['altas_actual'] ?? 0),
                        'anterior' => (int) ($movimientos['altas_anterior'] ?? 0),
                    ],
                    'bajas'                        => [
                        'actual'   => (int) ($movimientos['bajas_actual'] ?? 0),
                        'anterior' => (int) ($movimientos['bajas_anterior'] ?? 0),
                    ],
                    'porcentaje_ejercicios_usados' => (float) ($ejercicios_usados ?? 0),
                ],
                'graficos' => [
                    'objetivos'          => $objetivos,
                    'dias_semana'        => $dias_semana,
                    'frecuencia_semanal' => $frecuencia_semanal,
                    'top_ejercicios'     => $top_ejercicios,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('AdminController::dashboard: ' . $e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Error al cargar las estadísticas.'], 500);
        }
    }
}
