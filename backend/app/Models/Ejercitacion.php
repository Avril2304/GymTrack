<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ejercitacion extends Model
{
    protected $table = 'Ejercitacion';
    protected $primaryKey = 'ID_Ejercitacion';
    public $timestamps = false;

    protected $fillable = [
        'ID_Rutina',
        'ID_Ejercicio'
    ];

    public function rutina()
    {
        return $this->belongsTo(Rutina::class, 'ID_Rutina', 'ID_Rutina');
    }

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }
}
