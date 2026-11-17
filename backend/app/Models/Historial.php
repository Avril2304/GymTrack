<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Historial extends Model
{
    protected $table = 'Historial';
    protected $primaryKey = 'ID_Historial';
    public $timestamps = false;

    protected $fillable = [
        'ID_Usuario',
        'ID_Ejercicio',
        'fecha',
        'peso',
        'repeticiones',
        'series'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }
}
