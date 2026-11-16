<?php

namespace App\Http\Controllers;

use App\Models\Rutina;
use App\Models\Usuario;
use App\Models\Ejercitacion;
use App\Models\UsuarioRutinaDia;
use Illuminate\Http\Request;

class RutinaController
{
    public function obtenerRutinas(Request $request)
    {
        $uid = $request->query('user_id') ?: $request->query('id', 0);

        try {
            if ($uid > 0) {
                $usuario = Usuario::find($uid);
                $idClaseUsuario = $usuario ? $usuario->ID_Clase : 0;

                $rutinas = Rutina::with('clase')
                    ->whereNull('ID_Usuario')
                    ->where(function ($query) use ($idClaseUsuario) {
                        $query->where('ID_Clase', $idClaseUsuario)
                              ->orWhereHas('clase', function ($q) {
                                  $q->where('nombre', 'Universal');
                              });
                    })
                    ->get()
                    ->map(function ($r) use ($idClaseUsuario) {
                        $arr = $r->toArray();
                        $arr['tipo'] = ($r->ID_Clase == $idClaseUsuario) ? 'recomendada' : 'estandar';
                        $arr['nombre_clase'] = $r->clase ? $r->clase->nombre : '';
                        return $arr;
                    });
            } else {
                $rutinas = Rutina::whereNull('ID_Usuario')->get();
            }

            return response()->json(['ok' => true, 'data' => $rutinas]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function misRutinas(Request $request)
    {
        $uid = $request->query('user_id') ?: $request->query('id', 0);

        if (!$uid) {
            return response()->json(['ok' => false, 'msg' => 'Falta ID de usuario']);
        }

        try {
            $creadas = Rutina::where('ID_Usuario', $uid)->orderBy('nombre', 'asc')->get()->toArray();
            
            $favoritasIds = UsuarioRutinaDia::where('ID_Usuario', $uid)->pluck('ID_Rutina');
            if (is_object($favoritasIds) && method_exists($favoritasIds, 'toArray')) {
                $favoritasIds = $favoritasIds->toArray();
            }
            
            $favoritas = [];
            if (!empty($favoritasIds)) {
                $favoritas = Rutina::whereIn('ID_Rutina', $favoritasIds)->whereNull('ID_Usuario')->orderBy('nombre', 'asc')->get()->toArray();
            }

            return response()->json([
                'ok' => true,
                'creadas' => $creadas,
                'favoritas' => $favoritas
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function detalle(Request $request)
    {
        $rid = $request->query('id') ?: $request->query('ID_Rutina', 0);
        $uid = $request->query('user_id') ?: $request->query('ID_Usuario', 0);

        if (!$rid) {
            return response()->json(['ok' => false, 'msg' => 'Falta ID de rutina']);
        }

        try {
            $rutina = Rutina::find($rid);
            if (!$rutina) {
                return response()->json(['ok' => false, 'msg' => 'Rutina no encontrada']);
            }

            $esFavorita = false;
            if ($uid > 0) {
                $esFavorita = UsuarioRutinaDia::where('ID_Usuario', $uid)->where('ID_Rutina', $rid)->exists();
            }

            $ejercicios = $rutina->ejercicios;
            $resultEjercicios = [];
            if ($ejercicios) {
                foreach ($ejercicios as $ex) {
                    $arr = $ex->toArray();
                    $arr['video'] = $ex->gif;
                    $resultEjercicios[] = $arr;
                }
            }

            return response()->json([
                'ok' => true,
                'rutina' => $rutina->toArray(),
                'es_favorita' => $esFavorita,
                'ejercicios' => $resultEjercicios
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');
        $nombre = trim($request->input('nombre', ''));
        $ejercicios = $request->input('ejercicios', []);
        $rutinaId = $request->input('id_rutina') ?: $request->input('ID_Rutina', 0);

        if (!$uid || !$nombre || empty($ejercicios)) {
            return response()->json(['ok' => false, 'msg' => 'Faltan datos']);
        }

        try {
            if ($rutinaId > 0) {
                $rutina = Rutina::where('ID_Rutina', $rutinaId)->where('ID_Usuario', $uid)->first();
                if (!$rutina) {
                    return response()->json(['ok' => false, 'msg' => 'No autorizado']);
                }

                $rutina->update(['nombre' => $nombre]);
                Ejercitacion::where('ID_Rutina', $rutinaId)->delete();
                $targetId = $rutinaId;
                $msg = "Rutina actualizada";
            } else {
                $rutina = Rutina::create([
                    'ID_Usuario' => $uid,
                    'nombre' => $nombre,
                    'ID_Clase' => 1,
                    'descripcion' => 'Personalizada'
                ]);
                $targetId = $rutina->ID_Rutina;

                UsuarioRutinaDia::create([
                    'ID_Usuario' => $uid,
                    'ID_Rutina' => $targetId
                ]);
                $msg = "Rutina creada";
            }

            foreach ($ejercicios as $ej) {
                $ejId = is_array($ej) ? ($ej['id'] ?? $ej['ID_Ejercicio'] ?? null) : $ej;
                if ($ejId) {
                    Ejercitacion::create([
                        'ID_Rutina' => $targetId,
                        'ID_Ejercicio' => $ejId
                    ]);
                }
            }

            return response()->json(['ok' => true, 'msg' => $msg]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function toggleFavorito(Request $request)
    {
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario');
        $rid = $request->input('rutina_id') ?: $request->input('ID_Rutina');

        if (!$uid || !$rid) {
            return response()->json(['ok' => false, 'msg' => 'Parámetros faltantes']);
        }

        try {
            $existe = UsuarioRutinaDia::where('ID_Usuario', $uid)->where('ID_Rutina', $rid)->first();

            if ($existe) {
                $existe->delete();
                return response()->json(['ok' => true, 'favorito' => false, 'msg' => 'Eliminado de favoritas']);
            } else {
                UsuarioRutinaDia::create([
                    'ID_Usuario' => $uid,
                    'ID_Rutina' => $rid
                ]);
                return response()->json(['ok' => true, 'favorito' => true, 'msg' => 'Agregado a favoritas']);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request)
    {
        $rid = $request->input('id_rutina') ?: $request->input('rutina_id') ?: $request->input('ID_Rutina', 0);
        $uid = $request->input('user_id') ?: $request->input('ID_Usuario', 0);

        if (!$rid || !$uid) {
            return response()->json(['ok' => false, 'msg' => 'Faltan parámetros']);
        }

        try {
            $rutina = Rutina::where('ID_Rutina', $rid)->where('ID_Usuario', $uid)->first();

            if ($rutina) {
                Ejercitacion::where('ID_Rutina', $rid)->delete();
                UsuarioRutinaDia::where('ID_Rutina', $rid)->delete();
                $rutina->delete();
                return response()->json(['ok' => true, 'msg' => 'Rutina eliminada con éxito']);
            } else {
                $fav = UsuarioRutinaDia::where('ID_Usuario', $uid)->where('ID_Rutina', $rid)->first();
                if ($fav) {
                    $fav->delete();
                    return response()->json(['ok' => true, 'msg' => 'Rutina eliminada de favoritas']);
                }
            }

            return response()->json(['ok' => false, 'msg' => 'Rutina no encontrada o no autorizada']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }
}
