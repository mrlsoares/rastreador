<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rastreador extends Model
{
    use HasFactory;

    protected $table = 'rastreadores';

    protected $fillable = [
        'empresa_id',
        'imei',
        'nome',
        'placa',
        'modelo_veiculo',
        'descricao',
        'ativo',
        'ignicao',
        'em_panico',
        'ultimo_contato',
    ];

    protected $casts = [
        'ativo'          => 'boolean',
        'ignicao'        => 'boolean',
        'em_panico'      => 'boolean',
        'ultimo_contato' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
    }

    public function posicoes(): HasMany
    {
        return $this->hasMany(Posicao::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    public function ultimaPosicao(): HasOne
    {
        return $this->hasOne(Posicao::class)->latestOfMany('data_hora');
    }

    // Scope para rastreadores ativos
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
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
        if ($user && $user->hasRole('super-admin')) {
            return $query;
        }

        return $query->where('empresa_id', $user?->empresa_id);
    }
}
