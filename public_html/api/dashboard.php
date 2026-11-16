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
    $filtroU = $idGimnasio !== null ? "AND ID_Gimnasio = :g" : "";        // Usuario sin alias
    $filtroJoinU = $idGimnasio !== null ? "JOIN Usuario u ON u.ID_Usuario = h.ID_Usuario AND u.ID_Gimnasio = :g" : "";
    $bindG = $idGimnasio !== null ? ['g' => $idGimnasio] : [];

    // ---------- NÚMEROS ----------

    // 1. % de socios activos (asistieron en los últimos 7 días) — vía Asistencia
    $stmt = $pdo->prepare("
        SELECT ROUND(
          (SELECT COUNT(DISTINCT a.ID_Usuario) FROM Asistencia a WHERE a.fecha >= CURDATE() - INTERVAL 7 DAY $filtroA)
          / NULLIF((SELECT COUNT(*) FROM Usuario WHERE 1=1 $filtroU), 0) * 100
        , 1) AS valor
    ");
    $stmt->execute($bindG);
    $activos = $stmt->fetch()['valor'];

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
    $promedio_dia = $stmt->fetch()['valor'];

    // 3. % de socios nuevos vs. mes anterior
    $stmt = $pdo->prepare("
        SELECT
          (SELECT COUNT(*) FROM Usuario WHERE fecha_alta >= CURDATE() - INTERVAL 30 DAY $filtroU) AS actual,
          (SELECT COUNT(*) FROM Usuario WHERE fecha_alta >= CURDATE() - INTERVAL 60 DAY
             AND fecha_alta < CURDATE() - INTERVAL 30 DAY $filtroU) AS anterior
    ");
    $stmt->execute($bindG);
    $nuevos = $stmt->fetch();

    $pct_nuevos = ($nuevos && $nuevos['anterior'] > 0)
        ? round((($nuevos['actual'] - $nuevos['anterior']) / $nuevos['anterior']) * 100, 1)
        : null; // null = todavía no hay base de comparación (mes anterior sin altas)

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
    $actividades = $stmt->fetchAll();

    // 2. Asistencia por día de la semana — vía Asistencia (1=Domingo ... 7=Sábado, estándar MySQL)
    $stmt = $pdo->prepare("
        SELECT DAYOFWEEK(a.fecha) AS orden, COUNT(*) AS asistencias
        FROM Asistencia a
        WHERE 1=1 $filtroA
        GROUP BY orden
        ORDER BY orden
    ");
    $stmt->execute($bindG);
    $dias_semana = $stmt->fetchAll();

    // 3. Turno mañana vs. tarde — directo desde Asistencia.turno (ya calculado al guardar
    //    el entrenamiento; Historial.hora no se persiste, por eso NO se usa acá).
    $stmt = $pdo->prepare("
        SELECT
          CASE a.turno WHEN 'M' THEN 'Mañana' WHEN 'T' THEN 'Tarde' ELSE 'Sin dato' END AS turno,
          COUNT(*) AS cantidad
        FROM Asistencia a
        WHERE a.turno IS NOT NULL $filtroA
        GROUP BY a.turno
    ");
    $stmt->execute($bindG);
    $turnos = $stmt->fetchAll();

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
            'promedio_por_dia'             => (float) $promedio_dia,
            'porcentaje_nuevos_vs_mes'     => $pct_nuevos,
            'porcentaje_ejercicios_usados' => (float) $ejercicios_usados,
        ],
        'graficos' => [
            'actividades'    => $actividades,
            'dias_semana'    => $dias_semana,
            'turnos'         => $turnos,
            'top_ejercicios' => $top_ejercicios,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('dashboard: ' . $e->getMessage());
    gt_error(500, 'Error al cargar las estadísticas.');
}
