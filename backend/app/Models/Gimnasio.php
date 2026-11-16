<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gimnasio extends Model
{
    protected $table = 'Gimnasio';
    protected $primaryKey = 'ID_Gimnasio';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'mail',
        'fecha_alta',
        'activo',
        'ID_Usuario_Dueno'
    ];

    public function dueno()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario_Dueno', 'ID_Usuario');
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'ID_Gimnasio', 'ID_Gimnasio');
    }
}
