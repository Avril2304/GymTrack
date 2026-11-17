<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    protected $table = 'Clase';
    protected $primaryKey = 'ID_Clase';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'ID_Clase', 'ID_Clase');
    }

    public function rutinas()
    {
        return $this->hasMany(Rutina::class, 'ID_Clase', 'ID_Clase');
    }
}
