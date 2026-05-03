<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Esp32Telemetria extends Model
{
    use HasFactory;

    protected $table = 'esp32_telemetrias';

    public $timestamps = false; // Gerenciado manualmente ou via created_at do banco

    protected $fillable = [
        'esp32_dispositivo_id',
        'latitude',
        'longitude',
        'bateria_vcc',
        'temperatura',
        'velocidade',
        'payload_extra',
        'data_hora',
    ];

    protected $casts = [
        'latitude'      => 'float',
        'longitude'     => 'float',
        'bateria_vcc'   => 'float',
        'temperatura'   => 'float',
        'payload_extra' => 'array',
        'data_hora'     => 'datetime',
    ];

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Esp32Dispositivo::class, 'esp32_dispositivo_id');
    }
}
