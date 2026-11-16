<?php

namespace App\Http\Controllers;

use App\Models\Historial;
use Illuminate\Http\Request;

class HistorialController
{
    public function index(Request $request)
    {
        $uid = $request->query('user_id') ?: $request->query('id', 0);

        if (!$uid) {
            return response()->json(['ok' => false, 'msg' => 'Falta ID de usuario']);
        }

        $historial = Historial::with('ejercicio')
            ->where('ID_Usuario', $uid)
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($h) {
                $arr = $h->toArray();
                $arr['nombre_ejercicio'] = $h->ejercicio ? $h->ejercicio->nombre : 'Ejercicio';
                return $arr;
            });

        return response()->json(['ok' => true, 'data' => $historial]);
    }

    public function store(Request $request)
    {
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');
        $fecha = $request->input('fecha', date('Y-m-d'));
        $items = $request->input('ejercicios', []);

        if (!$uid) {
            return response()->json(['ok' => false, 'msg' => 'Falta ID de usuario']);
        }

        if (!empty($items) && is_array($items)) {
            foreach ($items as $it) {
                $ejId = $it['id_ejercicio'] ?? $it['ID_Ejercicio'] ?? $it['id'] ?? null;
                if ($ejId) {
                    Historial::create([
                        'ID_Usuario' => $uid,
                        'ID_Ejercicio' => $ejId,
                        'fecha' => $fecha,
                        'series' => $it['series'] ?? 1,
                        'repeticiones' => $it['reps'] ?? $it['repeticiones'] ?? 10,
                        'peso' => $it['peso'] ?? 0
                    ]);
                }
            }

            // Auto-llenado de Asistencia (aislado)
            try {
                $hora = $request->input('hora', date('H:i:s'));
                $turno = ($hora < '14:00:00') ? 'M' : 'T';
                $usuario = \App\Models\Usuario::where('ID_Usuario', $uid)->first();
                $idGym = $usuario ? $usuario->ID_Gimnasio : null;

                $pdo = \Illuminate\Database\Capsule\Manager::getPdo();
                $stmtAsist = $pdo->prepare("
                    INSERT INTO Asistencia (ID_Usuario, ID_Gimnasio, fecha, hora_entrada, turno)
                    VALUES (:u, :g, :f, :h, :t)
                    ON DUPLICATE KEY UPDATE hora_entrada = LEAST(IFNULL(hora_entrada, VALUES(hora_entrada)), VALUES(hora_entrada))
                ");
                $stmtAsist->execute([
                    'u' => $uid,
                    'g' => $idGym,
                    'f' => $fecha,
                    'h' => $hora,
                    't' => $turno
                ]);
            } catch (\Throwable $eAsist) {
                error_log("Error registrando asistencia en HistorialController: " . $eAsist->getMessage());
            }

            return response()->json(['ok' => true, 'msg' => 'Entrenamiento guardado']);
        }

        $ejId = $request->input('ejercicio_id') ?: $request->input('id_ejercicio');
        if ($ejId) {
            Historial::create([
                'ID_Usuario' => $uid,
                'ID_Ejercicio' => $ejId,
                'fecha' => $fecha,
                'series' => $request->input('series', 1),
                'repeticiones' => $request->input('repeticiones', $request->input('reps', 10)),
                'peso' => $request->input('peso', 0)
            ]);

            // Auto-llenado de Asistencia (aislado)
            try {
                $hora = $request->input('hora', date('H:i:s'));
                $turno = ($hora < '14:00:00') ? 'M' : 'T';
                $usuario = \App\Models\Usuario::where('ID_Usuario', $uid)->first();
                $idGym = $usuario ? $usuario->ID_Gimnasio : null;

                $pdo = \Illuminate\Database\Capsule\Manager::getPdo();
                $stmtAsist = $pdo->prepare("
                    INSERT INTO Asistencia (ID_Usuario, ID_Gimnasio, fecha, hora_entrada, turno)
                    VALUES (:u, :g, :f, :h, :t)
                    ON DUPLICATE KEY UPDATE hora_entrada = LEAST(IFNULL(hora_entrada, VALUES(hora_entrada)), VALUES(hora_entrada))
                ");
                $stmtAsist->execute([
                    'u' => $uid,
                    'g' => $idGym,
                    'f' => $fecha,
                    'h' => $hora,
                    't' => $turno
                ]);
            } catch (\Throwable $eAsist) {
                error_log("Error registrando asistencia en HistorialController: " . $eAsist->getMessage());
            }

            return response()->json(['ok' => true, 'msg' => 'Entrenamiento guardado']);
        }

        return response()->json(['ok' => false, 'msg' => 'Faltan ejercicios']);
    }

    public function destroy(Request $request)
    {
        $hid = $request->input('id') ?: $request->input('id_historial') ?: $request->input('ID_Historial');
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');

        $registro = Historial::where('ID_Historial', $hid)->where('ID_Usuario', $uid)->first();

        if (!$registro) {
            return response()->json(['ok' => false, 'msg' => 'Registro no encontrado']);
        }

        $registro->delete();

        return response()->json(['ok' => true, 'msg' => 'Registro eliminado']);
    }
}
