<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Historial;
use Illuminate\Http\Request;

class PerfilController
{
    public function show(Request $request)
    {
        $uid = $request->query('user_id') ?: $request->query('id', 0);

        if (!$uid) {
            return response()->json(['ok' => false, 'msg' => 'Falta ID de usuario']);
        }

        $usuario = Usuario::with('clase')->find($uid);

        if (!$usuario) {
            return response()->json(['ok' => false, 'msg' => 'Usuario no encontrado']);
        }

        $diasEntrenados = Historial::where('ID_Usuario', $uid)
            ->distinct('fecha')
            ->count('fecha');

        $edad = '--';
        $fn = $usuario->fecha_nacimiento;
        if ($fn && $fn !== '0000-00-00') {
            try {
                $bday = new \DateTime($fn);
                $today = new \DateTime();
                $edad = (string) $today->diff($bday)->y;
            } catch (\Throwable $e) {
                $edad = '--';
            }
        }

        $payload = [
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'nombre_completo' => trim($usuario->nombre . ' ' . $usuario->apellido),
            'email' => $usuario->mail,
            'peso' => (float) $usuario->peso,
            'altura' => (float) $usuario->altura,
            'dni' => $usuario->DNI,
            'telefono' => $usuario->telefono,
            'sexo' => $usuario->sexo,
            'fecha_nacimiento' => $usuario->fecha_nacimiento,
            'nombre_clase' => $usuario->clase ? $usuario->clase->nombre : null,
            'id_clase' => $usuario->ID_Clase,
            'foto' => $usuario->foto,
            'dias_entrenados' => $diasEntrenados,
            'edad' => $edad
        ];

        return response()->json([
            'ok' => true,
            'data' => $payload,
            'perfil' => $payload
        ]);
    }

    public function update(Request $request)
    {
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');
        $usuario = Usuario::find($uid);

        if (!$usuario) {
            return response()->json(['ok' => false, 'msg' => 'Usuario no encontrado']);
        }

        $pesoInput = $request->input('peso');
        $pesoFinal = $usuario->peso;
        if ($pesoInput !== null && $pesoInput !== '') {
            $p = floatval($pesoInput);
            $pesoFinal = ($p >= 30.0 && $p <= 350.0) ? $p : ($usuario->peso ?: 70.0);
        }

        $alturaInput = $request->input('altura');
        $alturaFinal = $usuario->altura;
        if ($alturaInput !== null && $alturaInput !== '') {
            $a = floatval($alturaInput);
            $alturaFinal = ($a >= 0.5 && $a <= 2.5) ? $a : ($usuario->altura ?: 1.70);
        }

        $usuario->fill([
            'nombre' => $request->input('nombre', $usuario->nombre),
            'apellido' => $request->input('apellido', $usuario->apellido),
            'peso' => $pesoFinal,
            'altura' => $alturaFinal,
            'telefono' => $request->input('telefono', $usuario->telefono),
            'sexo' => $request->input('sexo', $usuario->sexo),
            'ID_Clase' => $request->input('id_clase', $usuario->ID_Clase)
        ]);

        if ($request->input('mail')) {
            $usuario->mail = $request->input('mail');
        }

        $usuario->save();

        return response()->json([
            'ok' => true,
            'msg' => 'Perfil actualizado con éxito',
            'user' => $usuario
        ]);
    }

    public function subirFoto(Request $request)
    {
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');
        $usuario = Usuario::find($uid);

        if (!$usuario) {
            return response()->json(['ok' => false, 'msg' => 'Usuario no encontrado']);
        }

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $fileName = 'user_' . $uid . '_' . time() . '.' . pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            
            // Ruta exacta: 4 niveles arriba para salir de backend/ y apuntar a public_html/img/usuarios
            $targetDir = realpath(__DIR__ . '/../../../../public_html/img/usuarios')
                      ?: (__DIR__ . '/../../../../public_html/img/usuarios');

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }

            if ($file->move($targetDir, $fileName)) {
                $usuario->foto = $fileName;
                $usuario->save();
                return response()->json(['ok' => true, 'msg' => 'Foto actualizada', 'nueva_foto' => $fileName, 'foto' => $fileName]);
            }
        }

        return response()->json(['ok' => false, 'msg' => 'Error al subir la imagen']);
    }
}
