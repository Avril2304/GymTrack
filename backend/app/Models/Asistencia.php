<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $table = 'Asistencia';
    protected $primaryKey = 'ID_Asistencia';
    public $timestamps = false;

    protected $fillable = [
        'ID_Usuario',
        'ID_Gimnasio',
        'fecha',
        'hora_entrada',
        'turno'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    public function gimnasio()
    {
        return $this->belongsTo(Gimnasio::class, 'ID_Gimnasio', 'ID_Gimnasio');
    }
}
