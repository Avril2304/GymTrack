<?php
// Archivos/Php/cron_resumen_mensual.php
// Cron mensual (día 1, 9AM): envía resumen del mes anterior con métricas + párrafo IA
// Protegido por token — no se puede ejecutar sin el parámetro correcto

set_time_limit(300);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- PROTECCIÓN POR TOKEN ---
$tokenRecibido = $_GET['token'] ?? '';
if (empty($tokenRecibido) || $tokenRecibido !== CRON_TOKEN) {
    http_response_code(403);
    echo "Acceso denegado.";
    exit;
}

echo "=== CRON: Resumen Mensual === " . date('Y-m-d H:i:s') . "\n\n";

// --- Calcular rango del mes anterior ---
$mesPasadoInicio = date('Y-m-01', strtotime('first day of last month'));
$mesPasadoFin    = date('Y-m-t',  strtotime('last day of last month'));
$meses = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];
$numMes    = (int)date('m', strtotime($mesPasadoInicio));
$nombreMes = $meses[$numMes] . ' ' . date('Y', strtotime($mesPasadoInicio));

echo "Período: {$mesPasadoInicio} a {$mesPasadoFin} ({$nombreMes})\n";
echo str_repeat('-', 40) . "\n";

try {
    $stmt = $pdo->prepare("
        SELECT
            u.ID_Usuario,
            u.nombre,
            u.apellido,
            u.mail,
            c.nombre AS objetivo,
            COUNT(DISTINCT h.fecha) AS dias_entrenados,
            COUNT(h.ID_Historial)   AS series_totales,
            COALESCE(ROUND(SUM(h.peso * h.repeticiones * h.series), 1), 0) AS volumen_total_kg,
            COALESCE(MAX(h.peso), 0) AS peso_maximo
        FROM Usuario u
        LEFT JOIN Clase c ON u.ID_Clase = c.ID_Clase
        JOIN Historial h ON u.ID_Usuario = h.ID_Usuario
        WHERE
            h.fecha BETWEEN :inicio AND :fin
            AND u.es_admin = 'N'
            AND u.mail IS NOT NULL
            AND u.mail != ''
            AND u.mail LIKE '%@%'
        GROUP BY u.ID_Usuario, u.nombre, u.apellido, u.mail, c.nombre
        HAVING dias_entrenados > 0
    ");
    $stmt->execute([':inicio' => $mesPasadoInicio, ':fin' => $mesPasadoFin]);
    $usuarios = $stmt->fetchAll();

    $total    = count($usuarios);
    $enviados = 0;
    $errores  = 0;

    echo "Usuarios activos en {$nombreMes}: {$total}\n\n";

    $stmtUpdate = $pdo->prepare(
        "UPDATE Usuario SET ultimo_resumen = CURRENT_DATE WHERE ID_Usuario = ?"
    );

    foreach ($usuarios as $u) {
        $nombre         = trim($u['nombre']);
        $apellido       = trim($u['apellido']);
        $mail           = trim($u['mail']);
        $objetivo       = $u['objetivo'] ?? 'General';
        $diasEntrenados = (int)$u['dias_entrenados'];
        $volumen        = number_format((float)$u['volumen_total_kg'], 0, ',', '.');
        $pesoMax        = $u['peso_maximo'];

        echo "Procesando: {$nombre} {$apellido} <{$mail}> — {$diasEntrenados} días, {$volumen} kg\n";

        // Párrafo generado por OpenAI (con fallback si falla)
        $parrafoIA = generarParrafoIA($nombre, $diasEntrenados, $volumen, $pesoMax, $objetivo, $nombreMes);

        $cuerpoHTML = "
        <html><body style='font-family:Arial,sans-serif;background:#121212;color:#ffffff;padding:20px'>
            <div style='max-width:600px;margin:0 auto;background:#1c1c1e;border-radius:12px;padding:24px;border:1px solid #333'>
                <h2 style='color:#2D9CDB'>📊 Tu resumen de {$nombreMes}</h2>
                <p style='color:#ddd'>¡Hola, <strong>{$nombre}</strong>! Acá va tu resumen de entrenamiento del mes:</p>

                <div style='display:flex;gap:10px;margin:20px 0;flex-wrap:wrap'>
                    <div style='flex:1;min-width:110px;background:#252528;border-radius:8px;padding:14px;text-align:center;border-top:3px solid #2D9CDB'>
                        <div style='font-size:2rem;font-weight:bold;color:#2D9CDB'>{$diasEntrenados}</div>
                        <div style='color:#aaa;font-size:0.8rem;margin-top:4px'>días entrenados</div>
                    </div>
                    <div style='flex:1;min-width:110px;background:#252528;border-radius:8px;padding:14px;text-align:center;border-top:3px solid #27AE60'>
                        <div style='font-size:2rem;font-weight:bold;color:#27AE60'>{$volumen}</div>
                        <div style='color:#aaa;font-size:0.8rem;margin-top:4px'>kg de volumen</div>
                    </div>
                    <div style='flex:1;min-width:110px;background:#252528;border-radius:8px;padding:14px;text-align:center;border-top:3px solid #EB5757'>
                        <div style='font-size:2rem;font-weight:bold;color:#EB5757'>{$pesoMax}</div>
                        <div style='color:#aaa;font-size:0.8rem;margin-top:4px'>kg peso máx.</div>
                    </div>
                </div>

                <div style='background:#252528;border-radius:8px;padding:16px;border-left:4px solid #2D9CDB;margin:16px 0'>
                    <p style='color:#ddd;margin:0;line-height:1.7'>{$parrafoIA}</p>
                </div>

                <div style='text-align:center;margin-top:20px'>
                    <a href='https://gymtrack.com.ar'
                       style='display:inline-block;padding:14px 28px;background:#2D9CDB;color:white;border-radius:8px;text-decoration:none;font-weight:bold'>
                       Ver mi historial completo →
                    </a>
                </div>

                <p style='color:#555;font-size:12px;margin-top:24px;border-top:1px solid #333;padding-top:12px'>
                    GymTrack — Resumen mensual automático.
                </p>
            </div>
        </body></html>
        ";

        $cuerpoTexto = "{$nombre}: entrenaste {$diasEntrenados} días en {$nombreMes}. "
                     . "Volumen: {$volumen} kg. Peso máx: {$pesoMax} kg. "
                     . "Visitá gymtrack.com.ar para ver tu historial completo.";

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host        = MAIL_HOST;
            $mailer->SMTPAuth    = true;
            $mailer->Username    = MAIL_USERNAME;
            $mailer->Password    = MAIL_PASSWORD;
            $mailer->SMTPSecure  = MAIL_SECURE;
            $mailer->Port        = MAIL_PORT;
            $mailer->CharSet     = 'UTF-8';
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ];

            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($mail, "{$nombre} {$apellido}");
            $mailer->isHTML(true);
            $mailer->Subject = "📊 Tu resumen de {$nombreMes} en GymTrack";
            $mailer->Body    = $cuerpoHTML;
            $mailer->AltBody = $cuerpoTexto;

            $mailer->send();
            $stmtUpdate->execute([$u['ID_Usuario']]);
            $enviados++;
            echo "   [OK]    Mail enviado.\n";

        } catch (Exception $eM) {
            $errores++;
            echo "   [ERROR] " . $eM->getMessage() . "\n";
        }

        sleep(1); // 1 seg entre mails (OpenAI + SMTP llevan tiempo)
    }

    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('resumen_mensual', ?, ?, ?, ?)
    ")->execute([$total, $enviados, $errores, "Período: {$nombreMes}"]);

    echo "\n" . str_repeat('-', 40) . "\n";
    echo "RESUMEN: Procesados={$total} | Enviados={$enviados} | Errores={$errores}\n";
    echo "FIN: " . date('Y-m-d H:i:s') . "\n";

} catch (Throwable $e) {
    echo "[FATAL] " . $e->getMessage() . "\n";
    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('resumen_mensual', 0, 0, 1, ?)
    ")->execute(["Error fatal: " . $e->getMessage()]);
}

// --- Función: genera párrafo motivador con OpenAI ---
function generarParrafoIA($nombre, $dias, $volumen, $pesoMax, $objetivo, $mes) {
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if (empty($apiKey)) {
        return "¡{$nombre}, qué mes tremendo! Entrenaste {$dias} días y moviste {$volumen} kg en total. "
             . "Ese peso máximo de {$pesoMax} kg dice mucho de tu progreso. El próximo mes, apuntá a superar esas marcas. 💪";
    }

    $prompt = "Escribí un párrafo motivador en español para un socio del gimnasio llamado {$nombre}. "
            . "En {$mes} entrenó {$dias} días, levantó un volumen total de {$volumen} kg y su peso máximo fue {$pesoMax} kg. "
            . "Su objetivo de entrenamiento es: {$objetivo}. "
            . "El párrafo debe ser cálido, motivador, de no más de 4 oraciones, y darle 1 consejo concreto para mejorar el próximo mes según su objetivo. "
            . "No uses frases genéricas como 'sigue así'. Sé específico y concreto.";

    $payload = [
        'model'       => 'gpt-4o-mini',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
        'max_tokens'  => 150
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($apiKey)
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $texto = $data['choices'][0]['message']['content'] ?? null;
        if ($texto) return trim($texto);
    }

    // Fallback sin IA
    return "¡{$nombre}, qué mes tremendo! Entrenaste {$dias} días y moviste {$volumen} kg en total. "
         . "Ese peso máximo de {$pesoMax} kg dice mucho de tu progreso. El próximo mes, apuntá a superar esas marcas. 💪";
}
?>
