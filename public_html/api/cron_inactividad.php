<?php
// Archivos/Php/cron_inactividad.php
// Cron diario (10AM): detecta usuarios sin entrenar hace 5+ días y envía mail de recordatorio
// Protegido por token — no se puede ejecutar sin el parámetro correcto

set_time_limit(120);
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/conexion.php';
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

echo "=== CRON: Mail por Inactividad === " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Busca usuarios con más de 5 días sin entrenar (o que nunca entrenaron)
    // y que no hayan recibido mail de recordatorio en los últimos 5 días
    $stmt = $pdo->prepare("
        SELECT
            u.ID_Usuario,
            u.nombre,
            u.apellido,
            u.mail,
            MAX(h.fecha) AS ultima_sesion,
            u.ultimo_recordatorio
        FROM Usuario u
        LEFT JOIN Historial h ON u.ID_Usuario = h.ID_Usuario
        WHERE
            u.es_admin = 'N'
            AND u.mail IS NOT NULL
            AND u.mail != ''
            AND u.mail LIKE '%@%'
            AND (
                u.ultimo_recordatorio IS NULL
                OR DATEDIFF(CURRENT_DATE, u.ultimo_recordatorio) >= 5
            )
        GROUP BY u.ID_Usuario, u.nombre, u.apellido, u.mail, u.ultimo_recordatorio
        HAVING
            ultima_sesion IS NULL
            OR DATEDIFF(CURRENT_DATE, ultima_sesion) >= 5
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();

    $total    = count($usuarios);
    $enviados = 0;
    $errores  = 0;

    echo "Usuarios a notificar: {$total}\n";
    echo str_repeat('-', 40) . "\n";

    $stmtUpdate = $pdo->prepare(
        "UPDATE Usuario SET ultimo_recordatorio = CURRENT_DATE WHERE ID_Usuario = ?"
    );

    foreach ($usuarios as $u) {
        $nombre   = trim($u['nombre'] . ' ' . $u['apellido']);
        $mail     = trim($u['mail']);
        $ultimaSesion = $u['ultima_sesion'] ?? null;
        $diasSin  = $ultimaSesion ? (int)(new DateTime())->diff(new DateTime($ultimaSesion))->days : null;
        $diasTexto = $diasSin !== null ? "{$diasSin} días" : "varios días";

        $cuerpoHTML = "
        <html><body style='font-family:Arial,sans-serif;background:#121212;color:#ffffff;padding:20px'>
            <div style='max-width:600px;margin:0 auto;background:#1c1c1e;border-radius:12px;padding:24px;border:1px solid #333'>
                <h2 style='color:#2D9CDB'>🏋️ ¡Te extrañamos en GymTrack!</h2>
                <p style='color:#ddd'>¡Hola, <strong>{$u['nombre']}</strong>!</p>
                <p style='color:#ddd'>
                    Notamos que hace <strong style='color:#2D9CDB'>{$diasTexto}</strong>
                    que no registrás un entrenamiento en GymTrack.
                </p>
                <p style='color:#ddd'>
                    ¿Todo bien? Tu rutina te espera. Incluso una sesión corta de 30 minutos
                    puede marcar la diferencia. 💪
                </p>
                <div style='text-align:center;margin-top:24px'>
                    <a href='https://gymtrack.com.ar'
                       style='display:inline-block;padding:14px 28px;background:#2D9CDB;color:white;border-radius:8px;text-decoration:none;font-weight:bold;font-size:1rem'>
                       Volver a entrenar →
                    </a>
                </div>
                <p style='color:#555;font-size:12px;margin-top:24px;border-top:1px solid #333;padding-top:12px'>
                    GymTrack — Si no querés recibir estos recordatorios, contactá al administrador de tu gimnasio.
                </p>
            </div>
        </body></html>
        ";

        $cuerpoTexto = "¡Hola, {$u['nombre']}! Notamos que hace {$diasTexto} que no entrenás. "
                     . "Volvé a registrar tu entrenamiento en gymtrack.com.ar";

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
            $mailer->addAddress($mail, $nombre);
            $mailer->isHTML(true);
            $mailer->Subject = "🏋️ ¡{$u['nombre']}, tu rutina te espera en GymTrack!";
            $mailer->Body    = $cuerpoHTML;
            $mailer->AltBody = $cuerpoTexto;

            $mailer->send();
            $stmtUpdate->execute([$u['ID_Usuario']]);
            $enviados++;
            echo "[OK]    Mail enviado a: {$mail} (inactividad: {$diasTexto})\n";

        } catch (Exception $eM) {
            $errores++;
            echo "[ERROR] No se pudo enviar a {$mail}: {$eM->getMessage()}\n";
        }

        usleep(500000); // 0.5 seg entre mails
    }

    // Registrar en el log de auditoría
    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('inactividad', ?, ?, ?, ?)
    ")->execute([$total, $enviados, $errores, "Ejecutado " . date('Y-m-d H:i:s')]);

    echo str_repeat('-', 40) . "\n";
    echo "RESUMEN: Procesados={$total} | Enviados={$enviados} | Errores={$errores}\n";
    echo "FIN: " . date('Y-m-d H:i:s') . "\n";

} catch (Throwable $e) {
    echo "[FATAL] Error general: " . $e->getMessage() . "\n";
    $pdo->prepare("
        INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
        VALUES ('inactividad', 0, 0, 1, ?)
    ")->execute(["Error fatal: " . $e->getMessage()]);
}
?>
