<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjercicioExerciseDB extends Model
{
    protected $table = 'Ejercicio_ExerciseDB';
    protected $primaryKey = 'exercisedb_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'exercisedb_id',
        'ID_Ejercicio',
        'nombre_en',
        'body_part',
        'target_muscle',
        'equipment',
        'gif_url_original',
        'descargado_en'
    ];

    public function ejercicio()
    {
        return $this->belongsTo(Ejercicio::class, 'ID_Ejercicio', 'ID_Ejercicio');
    }
}
