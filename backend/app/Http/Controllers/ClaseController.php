<?php

namespace App\Http\Controllers;

use App\Models\Clase;
use Illuminate\Http\Request;

class ClaseController
{
    public function index()
    {
        $clases = Clase::orderBy('nombre', 'asc')->get();

        return response()->json([
            'ok' => true,
            'data' => $clases
        ]);
    }
}
