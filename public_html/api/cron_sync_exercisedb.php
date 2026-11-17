<?php
// Archivos/Php/cron_sync_exercisedb.php
// Cron bimestral (día 12 de meses impares, 3AM): sincroniza ejercicios nuevos de ExerciseDB
// Estrategia "Zero Waste": solo pide desde el offset = COUNT actual → mínimo gasto de API
// Protegido por token — no se puede ejecutar sin el parámetro correcto

set_time_limit(180);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_config.php';

// --- PROTECCIÓN POR TOKEN ---
$tokenRecibido = $_GET['token'] ?? '';
if (empty($tokenRecibido) || $tokenRecibido !== CRON_TOKEN) {
    http_response_code(403);
    echo "Acceso denegado.";
    exit;
}

echo "=== CRON: Sync ExerciseDB === " . date('Y-m-d H:i:s') . "\n\n";

// --- CONFIGURACIÓN ---
$LIMIT     = 100; // Ejercicios por página de la API
$MAX_PAGES = 5;   // Máximo de páginas por corrida (seguridad ante catálogos enormes)

try {
    // 1. Contar cuántos ejercicios hay actualmente en la BD
    $totalActual = (int)$pdo->query("SELECT COUNT(*) FROM Ejercicio_ExerciseDB")->fetchColumn();
    echo "Ejercicios en BD:  {$totalActual}\n";
    echo "Offset de inicio:  {$totalActual}\n\n";

    // 2. Statement de inserción seguro (ON DUPLICATE KEY = reintentable sin duplicados)
    $stmtInsert = $pdo->prepare("
        INSERT INTO Ejercicio_ExerciseDB
            (exercisedb_id, nombre_en, body_part, target_muscle, equipment, gif_url_original)
        VALUES
            (:id, :nombre, :body_part, :target, :equipment, :gif)
        ON DUPLICATE KEY UPDATE
            nombre_en        = VALUES(nombre_en),
            body_part        = VALUES(body_part),
            target_muscle    = VALUES(target_muscle),
            equipment        = VALUES(equipment),
            gif_url_original = VALUES(gif_url_original)
    ");

    $nuevos       = 0;
    $erroresPag   = 0;
    $offset       = $totalActual;
    $paginasVistas = 0;

    // 3. Loop de páginas: pide hasta que la API devuelva vacío o lleguemos al máximo
    do {
        $url = "https://exercisedb.p.rapidapi.com/exercises?limit={$LIMIT}&offset={$offset}";
        echo "Consultando API: offset={$offset} ... ";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-RapidAPI-Key: '  . RAPIDAPI_KEY,
                'X-RapidAPI-Host: ' . RAPIDAPI_HOST,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            echo "[ERROR HTTP {$httpCode}: {$curlError}]\n";
            $erroresPag++;
            break;
        }

        $ejercicios = json_decode($response, true);

        if (!is_array($ejercicios) || count($ejercicios) === 0) {
            echo "Sin nuevos ejercicios. Catálogo al día. ✅\n";
            break;
        }

        $countPagina = count($ejercicios);
        echo "{$countPagina} ejercicios recibidos. Insertando...\n";

        foreach ($ejercicios as $ej) {
            $idRaw = $ej['id'] ?? null;
            if (!$idRaw) continue;

            $gifUrl = $ej['gifUrl'] ?? "https://v2.exercisedb.io/image/{$idRaw}.gif";

            $stmtInsert->execute([
                ':id'        => $idRaw,
                ':nombre'    => $ej['name']      ?? '',
                ':body_part' => $ej['bodyPart']  ?? '',
                ':target'    => $ej['target']    ?? '',
                ':equipment' => $ej['equipment'] ?? '',
                ':gif'       => $gifUrl,
            ]);
            $nuevos++;
        }

        $offset       += $countPagina;
        $paginasVistas++;

        if ($paginasVistas < $MAX_PAGES) {
            usleep(300000); // 300ms entre páginas (rate limit)
        }

    } while ($paginasVistas < $MAX_PAGES);

    // 4. Conteo final
    $totalFinal = (int)$pdo->query("SELECT COUNT(*) FROM Ejercicio_ExerciseDB")->fetchColumn();

    // 5. Log de auditoría
    $detalle = "Antes={$totalActual} | Después={$totalFinal} | Páginas API={$paginasVistas}";
    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('sync_exercisedb', ?, 0, ?, ?)
    ")->execute([$nuevos, $erroresPag, $detalle]);

    echo "\n" . str_repeat('-', 40) . "\n";
    echo "RESUMEN:\n";
    echo "  Ejercicios antes:   {$totalActual}\n";
    echo "  Ejercicios después: {$totalFinal}\n";
    echo "  Nuevos insertados:  {$nuevos}\n";
    echo "  Páginas de API:     {$paginasVistas}\n";
    echo "  Errores:            {$erroresPag}\n";
    echo "FIN: " . date('Y-m-d H:i:s') . "\n";

} catch (Throwable $e) {
    echo "[FATAL] " . $e->getMessage() . "\n";
    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('sync_exercisedb', 0, 0, 1, ?)
    ")->execute(["Error fatal: " . $e->getMessage()]);
}
?>
