<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rastreador extends Model
{
    protected $table = 'rastreadores';

    protected $fillable = [
        'imei',
        'nome',
        'placa',
        'modelo_veiculo',
        'descricao',
        'ativo',
        'ultimo_contato',
    ];

    protected $casts = [
        'ativo'          => 'boolean',
        'ultimo_contato' => 'datetime',
    ];

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
}
