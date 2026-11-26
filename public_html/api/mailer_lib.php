<?php
// api/mailer_lib.php - Envio de correos transaccionales, verificacion de cuenta y avisos de gimnasio.
// Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
if (__FILE__ === ($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit;
}

if (!function_exists('gt_html_escape')) {
    function gt_html_escape($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gt_mailer_configurado')) {
    function gt_mailer_configurado() {
        $configPath = __DIR__ . '/mail_config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        $requeridas = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM'];
        foreach ($requeridas as $const) {
            if (!defined($const) || trim((string) constant($const)) === '') {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('gt_app_base_url')) {
    function gt_app_base_url() {
        if (defined('APP_URL') && trim((string) APP_URL) !== '') {
            return rtrim(trim((string) APP_URL), '/');
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $scheme = $https ? 'https' : 'http';

            $reqPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if (!is_string($reqPath) || $reqPath === '') {
                $reqPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
            }

            if (preg_match('#^(.*?)/api(?:/.*)?$#', $reqPath, $m)) {
                $prefix = $m[1];
            } else {
                $prefix = rtrim(dirname($reqPath), '/\\');
            }
            if ($prefix === '/' || $prefix === '\\' || $prefix === '.') {
                $prefix = '';
            }
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim($prefix, '/');
        }
        return 'https://gymtrack.com.ar';
    }
}

if (!function_exists('gt_enviar_mail')) {
    function gt_enviar_mail($destinatarioEmail, $destinatarioNombre, $asunto, $cuerpoHTML, $cuerpoTexto) {
        if (!gt_mailer_configurado()) {
            return ['ok' => false, 'error' => 'SMTP no configurado'];
        }

        require_once __DIR__ . '/PHPMailer/Exception.php';
        require_once __DIR__ . '/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/SMTP.php';

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host        = MAIL_HOST;
            $mailer->SMTPAuth    = true;
            $mailer->Username    = MAIL_USERNAME;
            $mailer->Password    = MAIL_PASSWORD;
            $mailer->SMTPSecure  = defined('MAIL_SECURE') ? MAIL_SECURE : 'ssl';
            $mailer->Port        = defined('MAIL_PORT') ? (int) MAIL_PORT : 465;
            $mailer->CharSet     = 'UTF-8';
            $mailer->Timeout     = 15;
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $fromName = defined('MAIL_FROM_NAME') && trim((string) MAIL_FROM_NAME) !== ''
                ? MAIL_FROM_NAME
                : 'GymTrack';

            $mailer->setFrom(MAIL_FROM, $fromName);
            $mailer->addAddress($destinatarioEmail, $destinatarioNombre);
            $mailer->isHTML(true);
            $mailer->Subject = $asunto;
            $mailer->Body    = $cuerpoHTML;
            $mailer->AltBody = $cuerpoTexto;

            $mailer->send();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            error_log('gt_enviar_mail: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('gt_log_automation')) {
    function gt_log_automation(PDO $pdo, $tipo, $procesados, $enviados, $errores, $detalle) {
        try {
            $pdo->prepare("
                INSERT INTO Automation_Log (tipo, procesados, enviados, errores, detalle)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$tipo, (int) $procesados, (int) $enviados, (int) $errores, $detalle]);
        } catch (\Throwable $e) {
            // La tabla Automation_Log es opcional en entornos locales sin tabla de logs
        }
    }
}

if (!function_exists('gt_verificacion_esquema_listo')) {
    function gt_verificacion_esquema_listo(PDO $pdo) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `Usuario` LIKE 'email_verificado'")->fetch();
            if (!$col) {
                return false;
            }
            $tbl = $pdo->query("SHOW TABLES LIKE 'Verificacion_Email'")->fetch();
            return (bool) $tbl;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gt_email_layout_html')) {
    function gt_email_layout_html($titulo, $contenidoHtml, $ctaUrl = null, $ctaTexto = null, $pie = null) {
        $tituloEsc = gt_html_escape($titulo);
        $ctaHtml = '';
        if ($ctaUrl !== null && $ctaUrl !== '' && $ctaTexto !== null && $ctaTexto !== '') {
            $urlEsc = gt_html_escape($ctaUrl);
            $btnEsc = gt_html_escape($ctaTexto);
            $ctaHtml = "
                <div style='text-align:center;margin-top:24px'>
                    <a href='{$urlEsc}'
                       style='display:inline-block;padding:14px 28px;background:#2D9CDB;color:#ffffff;border-radius:8px;text-decoration:none;font-weight:bold;font-size:1rem'>
                        {$btnEsc}
                    </a>
                </div>
            ";
        }
        $pieTexto = $pie !== null && $pie !== ''
            ? gt_html_escape($pie)
            : 'GymTrack — Tu asistente de entrenamiento.';

        return "
        <html><body style='font-family:Arial,sans-serif;background:#121212;color:#ffffff;padding:20px'>
            <div style='max-width:600px;margin:0 auto;background:#1c1c1e;border-radius:12px;padding:24px;border:1px solid #333'>
                <h2 style='color:#2D9CDB;margin-top:0'>{$tituloEsc}</h2>
                {$contenidoHtml}
                {$ctaHtml}
                <p style='color:#777;font-size:12px;margin-top:24px;border-top:1px solid #333;padding-top:12px'>
                    {$pieTexto}
                </p>
            </div>
        </body></html>
        ";
    }
}

if (!function_exists('gt_verificacion_crear_y_enviar')) {
    function gt_verificacion_crear_y_enviar(PDO $pdo, $userId, $nombre, $mail, $apellido = '', $marcarNoVerificado = true) {
        if (!gt_mailer_configurado()) {
            error_log('gt_verificacion_crear_y_enviar: SMTP no configurado en mail_config.php');
            return ['activo' => false, 'enviado' => false, 'motivo' => 'smtp_no_configurado'];
        }
        if (!gt_verificacion_esquema_listo($pdo)) {
            error_log('gt_verificacion_crear_y_enviar: falta ejecutar migracion 11_verificacion_mail.sql');
            return ['activo' => false, 'enviado' => false, 'motivo' => 'migracion_pendiente'];
        }

        $uid = (int) $userId;
        if ($marcarNoVerificado) {
            $pdo->prepare("UPDATE Usuario SET email_verificado = 'N' WHERE ID_Usuario = ?")->execute([$uid]);
        }

        // Invalidar tokens anteriores pendientes de este usuario
        $pdo->prepare("UPDATE Verificacion_Email SET usado_en = NOW() WHERE ID_Usuario = ? AND usado_en IS NULL")
            ->execute([$uid]);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $pdo->prepare("
            INSERT INTO Verificacion_Email (ID_Usuario, token_hash, expira_en)
            VALUES (?, ?, NOW() + INTERVAL 24 HOUR)
        ")->execute([$uid, $tokenHash]);

        $url = gt_app_base_url() . '/login.html?verificar_token=' . urlencode($token);
        $nombreEsc = gt_html_escape($nombre);
        $urlEsc = gt_html_escape($url);

        $contenido = "
            <p style='color:#ddd'>¡Hola, <strong>{$nombreEsc}</strong>!</p>
            <p style='color:#ddd'>
                Gracias por registrarte en <strong style='color:#2D9CDB'>GymTrack</strong>.
                Para activar tu cuenta y empezar a registrar tus entrenamientos, confirmá tu dirección de correo haciendo clic en el siguiente botón:
            </p>
            <p style='color:#999;font-size:13px;margin-top:16px'>
                Este enlace vence en 24 horas. Si el botón no funciona, copiá y pegá esta dirección en tu navegador:<br>
                <span style='color:#2D9CDB;word-break:break-all'>{$urlEsc}</span>
            </p>
        ";

        $cuerpoHTML = gt_email_layout_html(
            '🏋️ Confirmá tu cuenta en GymTrack',
            $contenido,
            $url,
            'Confirmar mi correo →',
            'Si no creaste una cuenta en GymTrack, podés ignorar este correo.'
        );

        $cuerpoTexto = "¡Hola, {$nombre}!\n\n"
            . "Confirmá tu cuenta en GymTrack abriendo este enlace (válido por 24 horas):\n"
            . "{$url}\n\n"
            . "Si no creaste una cuenta, ignorá este correo.";

        $nombreCompleto = trim($nombre . ' ' . $apellido);
        $res = gt_enviar_mail(
            $mail,
            $nombreCompleto !== '' ? $nombreCompleto : $mail,
            '🏋️ Confirmá tu cuenta en GymTrack',
            $cuerpoHTML,
            $cuerpoTexto
        );

        gt_log_automation(
            $pdo,
            'verificacion_email',
            1,
            $res['ok'] ? 1 : 0,
            $res['ok'] ? 0 : 1,
            $res['ok'] ? "Usuario ID={$uid} ({$mail})" : "Usuario ID={$uid} error: " . ($res['error'] ?? 'desconocido')
        );

        return [
            'activo'  => true,
            'enviado' => (bool) $res['ok'],
            'error'   => $res['error'] ?? null,
        ];
    }
}

if (!function_exists('gt_verificacion_confirmar')) {
    function gt_verificacion_confirmar(PDO $pdo, $token) {
        $token = trim((string) $token);
        if (preg_match('/^[a-f0-9]{64}$/i', $token) !== 1) {
            return [
                'status' => 400,
                'body'   => [
                    'ok'     => false,
                    'codigo' => 'TOKEN_INVALIDO',
                    'msg'    => 'El enlace de verificación no es válido.',
                ],
            ];
        }

        if (!gt_verificacion_esquema_listo($pdo)) {
            return [
                'status' => 503,
                'body'   => [
                    'ok'  => false,
                    'msg' => 'La verificación de correo todavía no está habilitada en la base de datos.',
                ],
            ];
        }

        $tokenHash = hash('sha256', strtolower($token));
        $usuarioIdLog = null;
        $respuesta = null;

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("
                SELECT v.ID_Verificacion, v.ID_Usuario, v.usado_en,
                       (v.expira_en < NOW()) AS expirado,
                       u.email_verificado, u.mail, u.nombre
                FROM Verificacion_Email v
                JOIN Usuario u ON u.ID_Usuario = v.ID_Usuario
                WHERE v.token_hash = ?
                LIMIT 1
                FOR UPDATE
            ");
            $st->execute([$tokenHash]);
            $row = $st->fetch();

            if (!$row) {
                $pdo->commit();
                return [
                    'status' => 400,
                    'body'   => [
                        'ok'     => false,
                        'codigo' => 'TOKEN_INVALIDO',
                        'msg'    => 'El enlace de verificación no es válido o fue reemplazado por uno más reciente.',
                    ],
                ];
            }

            if (($row['email_verificado'] ?? 'N') === 'S') {
                $pdo->commit();
                return [
                    'status' => 200,
                    'body'   => [
                        'ok'            => true,
                        'ya_verificado' => true,
                        'email'         => $row['mail'],
                        'msg'           => 'Tu cuenta ya estaba verificada. Ya podés iniciar sesión.',
                    ],
                ];
            }

            if ($row['usado_en'] !== null) {
                $pdo->commit();
                return [
                    'status' => 400,
                    'body'   => [
                        'ok'     => false,
                        'codigo' => 'TOKEN_USADO',
                        'email'  => $row['mail'],
                        'msg'    => 'Este enlace ya fue utilizado o reemplazado. Pedí un nuevo correo de confirmación.',
                    ],
                ];
            }

            if ((int) $row['expirado'] === 1) {
                $pdo->commit();
                return [
                    'status' => 410,
                    'body'   => [
                        'ok'     => false,
                        'codigo' => 'TOKEN_EXPIRADO',
                        'email'  => $row['mail'],
                        'msg'    => 'El enlace de verificación venció (dura 24 horas). Pedí uno nuevo para confirmar tu cuenta.',
                    ],
                ];
            }

            $usuarioIdLog = (int) $row['ID_Usuario'];
            $pdo->prepare("UPDATE Usuario SET email_verificado = 'S' WHERE ID_Usuario = ?")
                ->execute([$usuarioIdLog]);
            $pdo->prepare("UPDATE Verificacion_Email SET usado_en = NOW() WHERE ID_Usuario = ? AND usado_en IS NULL")
                ->execute([$usuarioIdLog]);

            $pdo->commit();

            $respuesta = [
                'status' => 200,
                'body'   => [
                    'ok'    => true,
                    'email' => $row['mail'],
                    'msg'   => '¡Correo verificado con éxito! Ya podés iniciar sesión en GymTrack.',
                ],
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if ($usuarioIdLog !== null) {
            gt_log_automation($pdo, 'verificacion_confirmada', 1, 1, 0, "Usuario ID={$usuarioIdLog}");
        }

        return $respuesta;
    }
}

if (!function_exists('gt_verificacion_reenviar')) {
    function gt_verificacion_reenviar(PDO $pdo, $mail) {
        $mail = trim((string) $mail);
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 400,
                'body'   => ['ok' => false, 'msg' => 'Ingresá un correo electrónico válido.'],
            ];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $k = 'throttle_reenviar_verif';
            $ahora = time();
            $historial = [];
            foreach (($_SESSION[$k] ?? []) as $t) {
                if ($t > $ahora - 300) {
                    $historial[] = $t;
                }
            }
            if (count($historial) >= 3) {
                $_SESSION[$k] = $historial;
                return [
                    'status' => 429,
                    'body'   => ['ok' => false, 'msg' => 'Esperá unos minutos antes de pedir otro correo de verificación.'],
                ];
            }
            $historial[] = $ahora;
            $_SESSION[$k] = $historial;
        }

        if (!gt_mailer_configurado() || !gt_verificacion_esquema_listo($pdo)) {
            return [
                'status' => 503,
                'body'   => ['ok' => false, 'msg' => 'El envío de correos de verificación no está configurado en este servidor.'],
            ];
        }

        $st = $pdo->prepare("
            SELECT ID_Usuario, nombre, apellido, mail, email_verificado
            FROM Usuario
            WHERE mail = ?
            LIMIT 1
        ");
        $st->execute([$mail]);
        $u = $st->fetch();

        $msgGenerico = 'Si el correo corresponde a una cuenta pendiente de verificación, te enviamos un nuevo enlace. Revisá tu casilla y la carpeta de spam.';

        if (!$u || ($u['email_verificado'] ?? 'S') === 'S') {
            return [
                'status' => 200,
                'body'   => ['ok' => true, 'msg' => $msgGenerico],
            ];
        }

        $stReciente = $pdo->prepare("
            SELECT 1
            FROM Verificacion_Email
            WHERE ID_Usuario = ? AND creado_en > NOW() - INTERVAL 60 SECOND
            LIMIT 1
        ");
        $stReciente->execute([(int) $u['ID_Usuario']]);
        if ($stReciente->fetch()) {
            return [
                'status' => 429,
                'body'   => ['ok' => false, 'msg' => 'Ya te enviamos un correo hace menos de un minuto. Revisá tu casilla (o spam) o esperá unos segundos.'],
            ];
        }

        $res = gt_verificacion_crear_y_enviar(
            $pdo,
            (int) $u['ID_Usuario'],
            $u['nombre'] ?? '',
            $u['mail'],
            $u['apellido'] ?? '',
            false
        );

        if (!$res['enviado']) {
            return [
                'status' => 500,
                'body'   => ['ok' => false, 'msg' => 'No pudimos enviar el correo en este momento. Probá nuevamente en unos minutos.'],
            ];
        }

        return [
            'status' => 200,
            'body'   => ['ok' => true, 'msg' => $msgGenerico],
        ];
    }
}

if (!function_exists('gt_notificar_cambio_gimnasio')) {
    function gt_notificar_cambio_gimnasio(PDO $pdo, $idUsuario, $idGimnasio, $tipo, $origen) {
        try {
            if (!gt_mailer_configurado()) {
                return;
            }

            $stUser = $pdo->prepare("SELECT nombre, apellido, mail FROM Usuario WHERE ID_Usuario = ? LIMIT 1");
            $stUser->execute([(int) $idUsuario]);
            $u = $stUser->fetch();
            if (!$u) {
                return;
            }

            $mail = trim((string) ($u['mail'] ?? ''));
            if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            if (substr(strtolower($mail), -12) === '@example.com') {
                return;
            }

            $stGym = $pdo->prepare("SELECT nombre, direccion FROM Gimnasio WHERE ID_Gimnasio = ? LIMIT 1");
            $stGym->execute([(int) $idGimnasio]);
            $g = $stGym->fetch();
            if (!$g) {
                return;
            }

            $nombreUsuario = trim((string) ($u['nombre'] ?? ''));
            $nombreCompleto = trim($nombreUsuario . ' ' . ($u['apellido'] ?? ''));
            $gymNombre = trim((string) ($g['nombre'] ?? 'tu gimnasio'));
            $gymDir = trim((string) ($g['direccion'] ?? ''));

            $uEsc = gt_html_escape($nombreUsuario !== '' ? $nombreUsuario : 'socio');
            $gymEsc = gt_html_escape($gymNombre);
            $dirEsc = $gymDir !== '' ? gt_html_escape($gymDir) : '';
            $appUrl = gt_app_base_url();

            if ($tipo === 'alta') {
                $asunto = "🏋️ ¡Bienvenido a {$gymNombre} en GymTrack!";
                $motivoHtml = $origen === 'solicitud'
                    ? "Tu solicitud para sumarte a <strong style='color:#2D9CDB'>{$gymEsc}</strong> fue aprobada por el administrador."
                    : "El administrador de <strong style='color:#2D9CDB'>{$gymEsc}</strong> te vinculó como socio en GymTrack.";
                $motivoTxt = $origen === 'solicitud'
                    ? "Tu solicitud para sumarte a {$gymNombre} fue aprobada por el administrador."
                    : "El administrador de {$gymNombre} te vinculó como socio en GymTrack.";
                $extraDirHtml = $dirEsc !== ''
                    ? "<p style='color:#aaa;font-size:14px'>📍 Dirección del gimnasio: <strong style='color:#fff'>{$dirEsc}</strong></p>"
                    : '';

                $contenido = "
                    <p style='color:#ddd'>¡Hola, <strong>{$uEsc}</strong>!</p>
                    <p style='color:#ddd'>{$motivoHtml}</p>
                    {$extraDirHtml}
                    <p style='color:#ddd'>Ya podés entrar a GymTrack para registrar tus entrenamientos, ver tus rutinas y seguir tu progreso.</p>
                ";
                $cuerpoTexto = "¡Hola, {$nombreUsuario}! {$motivoTxt} Ingresá a {$appUrl} para continuar entrenando.";
            } else {
                $asunto = "🏋️ Actualización de gimnasio en tu cuenta de GymTrack";
                $motivoHtml = $origen === 'socio'
                    ? "Confirmamos tu salida de <strong style='color:#2D9CDB'>{$gymEsc}</strong>."
                    : "El administrador de <strong style='color:#2D9CDB'>{$gymEsc}</strong> te desvinculó de su gimnasio.";
                $motivoTxt = $origen === 'socio'
                    ? "Confirmamos tu salida de {$gymNombre}."
                    : "El administrador de {$gymNombre} te desvinculó de su gimnasio.";

                $contenido = "
                    <p style='color:#ddd'>¡Hola, <strong>{$uEsc}</strong>!</p>
                    <p style='color:#ddd'>{$motivoHtml}</p>
                    <p style='color:#ddd'>Tu cuenta sigue activa con todo tu historial de entrenamientos y tus rutinas intactas. Cuando quieras podés unirte a otro gimnasio desde la aplicación usando su identificador.</p>
                ";
                $cuerpoTexto = "¡Hola, {$nombreUsuario}! {$motivoTxt} Tu historial y rutinas siguen intactos en {$appUrl}.";
            }

            $cuerpoHTML = gt_email_layout_html(
                $asunto,
                $contenido,
                $appUrl,
                'Ir a GymTrack →',
                'GymTrack — Notificación automática de tu cuenta.'
            );

            $res = gt_enviar_mail(
                $mail,
                $nombreCompleto !== '' ? $nombreCompleto : $mail,
                $asunto,
                $cuerpoHTML,
                $cuerpoTexto
            );

            gt_log_automation(
                $pdo,
                'cambio_gimnasio',
                1,
                $res['ok'] ? 1 : 0,
                $res['ok'] ? 0 : 1,
                "tipo={$tipo} origen={$origen} uid={$idUsuario} gym={$idGimnasio}" . ($res['ok'] ? '' : ' err=' . ($res['error'] ?? ''))
            );
        } catch (\Throwable $e) {
            error_log('gt_notificar_cambio_gimnasio: ' . $e->getMessage());
        }
    }
}
