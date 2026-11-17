<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;

class AuthController
{
    public function login(Request $request)
    {
        $mail = trim($request->input('mail') ?: $request->input('email'));
        $clave = $request->input('clave') ?: $request->input('password');

        if (!$mail || !$clave) {
            return response()->json(['ok' => false, 'msg' => 'Email y contraseña requeridos']);
        }

        // -------------------------------------------------------------------
        // Rate Limiting (por mail e IP: máximo 5 intentos fallidos en 15 minutos)
        // -------------------------------------------------------------------
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limiteSuperado = false;

        try {
            $pdo = Capsule::getPdo();
            $pdo->prepare("DELETE FROM login_intentos WHERE fecha_intento < NOW() - INTERVAL 15 MINUTE")->execute();

            $stmtRate = $pdo->prepare("SELECT COUNT(*) FROM login_intentos WHERE (mail = ? OR ip = ?) AND fecha_intento >= NOW() - INTERVAL 15 MINUTE");
            $stmtRate->execute([$mail, $ip]);
            $intentosFallidos = (int) $stmtRate->fetchColumn();

            if ($intentosFallidos >= 5) {
                $limiteSuperado = true;
            }
        } catch (\Throwable $eRate) {
            // Fallback a sesión en caso de que la tabla aún no se haya creado
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $kSession = 'throttle_login_' . md5($ip . '_' . $mail);
            $ahora = time();
            $historial = [];
            foreach (($_SESSION[$kSession] ?? []) as $t) {
                if ($t > $ahora - 900) {
                    $historial[] = $t;
                }
            }
            if (count($historial) >= 5) {
                $limiteSuperado = true;
            }
        }

        if ($limiteSuperado) {
            return response()->json([
                'ok' => false,
                'msg' => 'Demasiados intentos fallidos. Por favor, esperá 15 minutos antes de volver a intentar.'
            ], 429);
        }

        // -------------------------------------------------------------------
        // Búsqueda de Usuario y Mitigación de Timing Attacks (Hash Dummy)
        // -------------------------------------------------------------------
        $usuario = Usuario::where('mail', $mail)->first();
        $esValido = false;
        $dummyHash = '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUV0123456789';

        if ($usuario) {
            if (password_verify($clave, $usuario->clave)) {
                $esValido = true;
            } elseif ($usuario->clave === $clave) {
                $usuario->update(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);
                $esValido = true;
            } elseif (hash('sha256', $clave) === $usuario->clave) {
                $usuario->update(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);
                $esValido = true;
            }
        } else {
            // Tiempo constante de procesamiento aunque no exista el usuario
            password_verify($clave, $dummyHash);
        }

        if (!$esValido) {
            // Registrar intento fallido
            try {
                $pdo = Capsule::getPdo();
                $pdo->prepare("INSERT INTO login_intentos (mail, ip) VALUES (?, ?)")->execute([$mail, $ip]);
            } catch (\Throwable $eLog) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $kSession = 'throttle_login_' . md5($ip . '_' . $mail);
                $historial = $_SESSION[$kSession] ?? [];
                $historial[] = time();
                $_SESSION[$kSession] = $historial;
            }

            return response()->json(['ok' => false, 'msg' => 'Email o contraseña incorrectos']);
        }

        // -------------------------------------------------------------------
        // Login Exitoso: limpiar intentos y regenerar sesión
        // -------------------------------------------------------------------
        try {
            $pdo = Capsule::getPdo();
            $pdo->prepare("DELETE FROM login_intentos WHERE mail = ? OR ip = ?")->execute([$mail, $ip]);
        } catch (\Throwable $eClear) {
            if (isset($_SESSION)) {
                $kSession = 'throttle_login_' . md5($ip . '_' . $mail);
                unset($_SESSION[$kSession]);
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('gt_iniciar_sesion')) {
                gt_iniciar_sesion();
            } else {
                session_start();
            }
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $usuario->ID_Usuario;
        $_SESSION['es_admin'] = $usuario->es_admin ?? 'N';

        // Determinar gimnasio para UI
        $idGimnasioUi = $usuario->ID_Gimnasio ? (int) $usuario->ID_Gimnasio : null;
        if (($usuario->es_admin ?? 'N') === 'S') {
            try {
                $gymDueno = \App\Models\Gimnasio::where('ID_Usuario_Dueno', $usuario->ID_Usuario)->first();
                if ($gymDueno) {
                    $idGimnasioUi = (int) $gymDueno->ID_Gimnasio;
                }
            } catch (\Throwable $eGym) {
                // Si el modelo o tabla no está disponible, continuar
            }
        }
        $_SESSION['id_gimnasio'] = $idGimnasioUi;

        return response()->json([
            'ok' => true,
            'msg' => 'Bienvenido ' . $usuario->nombre,
            'user' => [
                'ID_Usuario'  => $usuario->ID_Usuario,
                'nombre'      => $usuario->nombre,
                'apellido'    => $usuario->apellido,
                'email'       => $usuario->mail,
                'mail'        => $usuario->mail,
                'foto'        => $usuario->foto,
                'DNI'         => $usuario->DNI,
                'telefono'    => $usuario->telefono,
                'sexo'        => $usuario->sexo,
                'es_admin'    => $usuario->es_admin ?? 'N',
                'ID_Gimnasio' => $idGimnasioUi
            ]
        ]);
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return response()->json(['ok' => false, 'msg' => 'Método no permitido. Utilice POST.'], 405);
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('gt_iniciar_sesion')) {
                gt_iniciar_sesion();
            } else {
                session_start();
            }
        }

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function register(Request $request)
    {
        $nombre = trim($request->input('nombre'));
        $apellido = trim($request->input('apellido', ''));
        $mail = trim($request->input('mail') ?: $request->input('email'));
        $clave = $request->input('clave') ?: $request->input('password');
        $dni = trim($request->input('dni') ?: $request->input('DNI') ?: '');
        $fecha_nacimiento = $request->input('fecha_nacimiento') ?: date('Y-m-d');
        $sexo = $request->input('sexo', '');

        if (!$nombre || !$mail || !$clave) {
            return response()->json(['ok' => false, 'msg' => 'Faltan campos obligatorios']);
        }

        $existe = Usuario::where('mail', $mail)->exists();
        if ($existe) {
            return response()->json(['ok' => false, 'msg' => 'El correo electrónico ya está registrado']);
        }

        $hashClave = password_hash($clave, PASSWORD_DEFAULT);

        $telefono = trim($request->input('telefono', ''));
        $foto = trim($request->input('foto', ''));
        $id_clase = $request->input('id_clase') ?: $request->input('ID_Clase') ?: 1;
        
        $pesoRaw = floatval($request->input('peso') ?: 70.0);
        $peso = ($pesoRaw >= 30.0 && $pesoRaw <= 350.0) ? $pesoRaw : 70.0;
        
        $alturaRaw = floatval($request->input('altura') ?: 1.70);
        $altura = ($alturaRaw >= 0.5 && $alturaRaw <= 2.5) ? $alturaRaw : 1.70;

        $usuario = Usuario::create([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'mail' => $mail,
            'clave' => $hashClave,
            'DNI' => $dni,
            'fecha_nacimiento' => $fecha_nacimiento,
            'sexo' => $sexo ?: 'M',
            'telefono' => $telefono,
            'foto' => $foto,
            'ID_Clase' => $id_clase,
            'peso' => $peso,
            'altura' => $altura,
            'fecha_alta' => date('Y-m-d')
        ]);

        return response()->json([
            'ok' => true,
            'msg' => 'Usuario registrado exitosamente',
            'user_id' => $usuario->ID_Usuario
        ]);
    }
}
