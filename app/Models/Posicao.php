<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Posicao extends Model
{
    protected $table = 'posicoes';

    public $timestamps = false; // Apenas created_at (definido no banco)

    protected $fillable = [
        'rastreador_id',
        'data_hora',
        'latitude',
        'longitude',
        'velocidade',
        'angulo',
        'sinal_gps',
        'raw_data',
        'ignorada',
    ];

    protected $casts = [
        'data_hora'  => 'datetime',
        'latitude'   => 'float',
        'longitude'  => 'float',
        'velocidade' => 'integer',
        'angulo'     => 'integer',
        'sinal_gps'  => 'integer',
        'ignorada'   => 'boolean',
        'created_at' => 'datetime',
    ];

    public function rastreador(): BelongsTo
    {
        return $this->belongsTo(Rastreador::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(\App\Models\Evento::class);
    }

    // Scopes de filtro
    public function scopePeriodo($query, $inicio, $fim)
    {
        return $query->whereBetween('data_hora', [$inicio, $fim]);
    }

    public function scopeValidas($query)
    {
        return $query->where('ignorada', false)
                     ->whereNotNull('latitude')
                     ->whereNotNull('longitude');
    }

    // Formata velocidade para exibição
    public function getVelocidadeFormatadaAttribute(): string
    {
        return $this->velocidade . ' km/h';
    }

    // Retorna direção em texto (N, NE, L, SE, S, SO, O, NO)
    public function getDirecaoAttribute(): string
    {
        $direcoes = ['N', 'NE', 'L', 'SE', 'S', 'SO', 'O', 'NO'];
        return $direcoes[round($this->angulo / 45) % 8];
    }
}
