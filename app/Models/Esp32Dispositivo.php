<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Esp32Dispositivo extends Model
{
    use HasFactory;

    protected $table = 'esp32_dispositivos';

    protected $fillable = [
        'identificador',
        'nome',
        'descricao',
        'ativo',
        'ultimo_contato',
    ];

    protected $casts = [
        'ativo'          => 'boolean',
        'ultimo_contato' => 'datetime',
    ];

    public function telemetrias(): HasMany
    {
        return $this->hasMany(Esp32Telemetria::class, 'esp32_dispositivo_id');
    }

    public function ultimaTelemetria(): HasOne
    {
        return $this->hasOne(Esp32Telemetria::class, 'esp32_dispositivo_id')->latestOfMany('data_hora');
    }
}
