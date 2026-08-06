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
        'empresa_id',
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

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
    }

    public function empresa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Restringe à empresa do usuário (multi-tenant). Admin (role 'admin')
     * enxerga todas as empresas.
     */
    public function scopeDaEmpresaDoUsuario($query, ?\App\Models\User $user)
    {
        if ($user && $user->hasRole('admin')) {
            return $query;
        }

        return $query->where('empresa_id', $user?->empresa_id);
    }

    public function telemetrias(): HasMany
    {
        return $this->hasMany(Esp32Telemetria::class, 'esp32_dispositivo_id');
    }

    public function ultimaTelemetria(): HasOne
    {
        return $this->hasOne(Esp32Telemetria::class, 'esp32_dispositivo_id')->latestOfMany('data_hora');
    }
}
