<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioRutinaDia extends Model
{
    protected $table = 'Usuario_RutinaDia';
    protected $primaryKey = 'ID_URD';
    public $timestamps = false;

    protected $fillable = [
        'ID_Usuario',
        'ID_Rutina'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function rutina()
    {
        return $this->belongsTo(Rutina::class, 'ID_Rutina', 'ID_Rutina');
    }
}
