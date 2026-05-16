<?php
/*
 * Created At: 2026-05-12T12:39:15Z
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Telemetria extends Model
{
    protected $table = 'telemetrias';

    protected $fillable = [
        'device_id',
        'latitude',
        'longitude',
        'velocidade',
        'bateria',
        'panic_button',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'velocidade' => 'float',
        'bateria' => 'float',
        'panic_button' => 'boolean',
    ];
}
