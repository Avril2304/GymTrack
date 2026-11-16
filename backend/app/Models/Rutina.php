<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rutina extends Model
{
    protected $table = 'Rutina';
    protected $primaryKey = 'ID_Rutina';
    public $timestamps = false;

    protected $fillable = [
        'ID_Clase',
        'ID_Usuario',
        'nombre',
        'descripcion'
    ];

    public function clase()
    {
        return $this->belongsTo(Clase::class, 'ID_Clase', 'ID_Clase');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function ejercitaciones()
    {
        return $this->hasMany(Ejercitacion::class, 'ID_Rutina', 'ID_Rutina');
    }

    public function ejercicios()
    {
        return $this->belongsToMany(
            Ejercicio::class,
            'Ejercitacion',
            'ID_Rutina',
            'ID_Ejercicio'
        );
    }
}
