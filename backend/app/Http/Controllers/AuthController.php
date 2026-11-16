<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
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

        $usuario = Usuario::where('mail', $mail)->first();

        if (!$usuario) {
            return response()->json(['ok' => false, 'msg' => 'Usuario no encontrado']);
        }

        // Verificación de contraseña con soporte para password_verify, SHA-256 y texto plano
        $esValido = false;
        if (password_verify($clave, $usuario->clave)) {
            $esValido = true;
        } elseif ($usuario->clave === $clave) {
            $usuario->update(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);
            $esValido = true;
        } elseif (hash('sha256', $clave) === $usuario->clave) {
            $usuario->update(['clave' => password_hash($clave, PASSWORD_DEFAULT)]);
            $esValido = true;
        }

        if (!$esValido) {
            return response()->json(['ok' => false, 'msg' => 'Contraseña incorrecta']);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = (int) $usuario->ID_Usuario;
        $_SESSION['es_admin'] = $usuario->es_admin ?? 'N';
        $_SESSION['id_gimnasio'] = $usuario->ID_Gimnasio ? (int) $usuario->ID_Gimnasio : null;

        return response()->json([
            'ok' => true,
            'msg' => 'Bienvenido ' . $usuario->nombre,
            'user' => [
                'ID_Usuario' => $usuario->ID_Usuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email' => $usuario->mail,
                'mail' => $usuario->mail,
                'foto' => $usuario->foto,
                'DNI' => $usuario->DNI,
                'telefono' => $usuario->telefono,
                'sexo' => $usuario->sexo,
                'es_admin' => $usuario->es_admin ?? 'N',
                'ID_Gimnasio' => $usuario->ID_Gimnasio ?? null
            ]
        ]);
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
