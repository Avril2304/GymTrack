<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ejercicio extends Model
{
    protected $table = 'Ejercicio';
    protected $primaryKey = 'ID_Ejercicio';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'gif',
        'body_part_es',
        'target_muscle_es',
        'equipment_es'
    ];

    public function ejercitaciones()
    {
        return $this->hasMany(Ejercitacion::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }

    public function historial()
    {
        return $this->hasMany(Historial::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }

    public function exerciseDb()
    {
        return $this->hasOne(EjercicioExerciseDB::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }
}
