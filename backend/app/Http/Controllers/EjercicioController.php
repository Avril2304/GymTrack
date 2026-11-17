<?php

namespace App\Http\Controllers;

use App\Models\Ejercicio;
use Illuminate\Http\Request;

class EjercicioController
{
    public function index()
    {
        $ejercicios = Ejercicio::orderBy('nombre', 'asc')->get();

        return response()->json([
            'ok' => true,
            'data' => $ejercicios
        ]);
    }
}
