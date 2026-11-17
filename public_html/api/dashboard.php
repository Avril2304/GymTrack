<?php
// api/dashboard.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'sesion.php';
gt_iniciar_sesion();
require_once 'conexion.php';

try {
    // Solo el administrador logueado, y solo con las metricas de SU gimnasio (ver sesion.php).
    // El cliente no puede elegir otro gimnasio ni pedir las metricas globales.
    $admin = gt_requerir_admin_con_gimnasio($pdo);
    $idGimnasio = $admin['ID_Gimnasio'];

    // Fragmentos de filtro por gimnasio, reutilizados en cada consulta.
    // Con :g repetido, PDO liga el mismo valor a todas las apariciones.
    $filtroA = $idGimnasio !== null ? "AND a.ID_Gimnasio = :g" : "";       // alias Asistencia
    $filtroJoinU = $idGimnasio !== null ? "JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario AND u.ID_Gimnasio = :g" : "";
    $bindG = $idGimnasio !== null ? ['g' => $idGimnasio] : [];

    // "Socios" = usuarios del gimnasio que no son administradores.
    // Las métricas de socios se filtran por el gimnasio del socio (Usuario.ID_Gimnasio);
    // las de entrenamientos, por el gimnasio donde se registró cada uno (Asistencia.ID_Gimnasio).
    $filtroSocio = "u.es_admin = 'N'" . ($idGimnasio !== null ? " AND u.ID_Gimnasio = :g" : "");

    // ---------- NÚMEROS ----------

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
    $fila = $stmt->fetch();
    $activos_cant = (int) $fila['activos'];
    $socios_total = (int) $fila['total'];
    $activos = $socios_total > 0 ? round($activos_cant / $socios_total * 100, 1) : 0;

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
    $movimientos = $stmt->fetch();

    // 4. % de ejercicios del catálogo que se usaron alguna vez
    //    (numerador acotado al gym si corresponde; el catálogo de Ejercicio es global)
    $stmt = $pdo->prepare("
        SELECT ROUND(
          (SELECT COUNT(DISTINCT h.ID_Ejercicio) FROM Historial h $filtroJoinU)
          / NULLIF((SELECT COUNT(*) FROM Ejercicio), 0) * 100
        , 1) AS valor
    ");
    $stmt->execute($bindG);
    $ejercicios_usados = $stmt->fetch()['valor'];

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
    $objetivos = $stmt->fetchAll();

    // 2. Entrenamientos por día de la semana — vía Asistencia (1=Domingo ... 7=Sábado, estándar MySQL)
    $stmt = $pdo->prepare("
        SELECT DAYOFWEEK(a.fecha) AS orden, COUNT(*) AS asistencias
        FROM Asistencia a
        WHERE 1=1 $filtroA
        GROUP BY orden
        ORDER BY orden
    ");
    $stmt->execute($bindG);
    $dias_semana = $stmt->fetchAll();

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
    foreach ($stmt->fetchAll() as $fila) {
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
    $top_ejercicios = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'numeros' => [
            'porcentaje_activos'           => (float) $activos,
            'socios_activos'               => $activos_cant,
            'socios_total'                 => $socios_total,
            'socios_nuevos'                => [
                'actual'   => (int) ($movimientos['altas_actual'] ?? 0),
                'anterior' => (int) ($movimientos['altas_anterior'] ?? 0),
            ],
            'bajas'                        => [
                'actual'   => (int) ($movimientos['bajas_actual'] ?? 0),
                'anterior' => (int) ($movimientos['bajas_anterior'] ?? 0),
            ],
            'porcentaje_ejercicios_usados' => (float) $ejercicios_usados,
        ],
        'graficos' => [
            'objetivos'          => $objetivos,
            'dias_semana'        => $dias_semana,
            'frecuencia_semanal' => $frecuencia_semanal,
            'top_ejercicios'     => $top_ejercicios,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('dashboard: ' . $e->getMessage());
    gt_error(500, 'Error al cargar las estadísticas.');
}
