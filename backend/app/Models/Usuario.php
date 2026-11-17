<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'Usuario';
    protected $primaryKey = 'ID_Usuario';
    public $timestamps = false;

    protected $fillable = [
        'ID_Clase',
        'ID_Gimnasio',
        'nombre',
        'apellido',
        'peso',
        'altura',
        'DNI',
        'fecha_nacimiento',
        'sexo',
        'mail',
        'telefono',
        'clave',
        'foto'
    ];

    protected $hidden = [
        'clave'
    ];

    public function clase()
    {
        return $this->belongsTo(Clase::class, 'ID_Clase', 'ID_Clase');
    }

    public function gimnasio()
    {
        return $this->belongsTo(Gimnasio::class, 'ID_Gimnasio', 'ID_Gimnasio');
    }

    public function rutinas()
    {
        return $this->hasMany(Rutina::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function historial()
    {
        return $this->hasMany(Historial::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function rutinasFavoritas()
    {
        return $this->hasMany(UsuarioRutinaDia::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'ID_Usuario', 'ID_Usuario');
    }
}
