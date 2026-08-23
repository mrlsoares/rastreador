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

    // Nunca serializa o hash do token.
    protected $hidden = [
        'api_token',
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
     * Restringe à empresa do usuário (multi-tenant). 'super-admin' e 'leitor'
     * (monitor read-only global) enxergam todas as empresas.
     */
    public function scopeDaEmpresaDoUsuario($query, ?\App\Models\User $user)
    {
        if ($user && $user->hasAnyRole(['super-admin', 'leitor'])) {
            return $query;
        }

        return $query->where('empresa_id', $user?->empresa_id);
    }

    /**
     * Gera um novo token de ingestão. Guarda apenas o hash SHA-256 e os 4
     * últimos dígitos; devolve o token em claro UMA vez.
     */
    public function gerarTokenApi(): string
    {
        $plain = \Illuminate\Support\Str::random(48);

        $this->forceFill([
            'api_token'   => hash('sha256', $plain),
            'token_last4' => substr($plain, -4),
        ])->save();

        return $plain;
    }

    /**
     * Localiza um dispositivo pelo token em claro (compara o hash).
     */
    public static function porToken(string $plain): ?self
    {
        return static::where('api_token', hash('sha256', $plain))->first();
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
