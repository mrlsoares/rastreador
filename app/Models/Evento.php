<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    public $timestamps = false;

    protected $fillable = [
        'rastreador_id',
        'posicao_id',
        'tipo',
        'descricao',
        'codigo_raw',
        'botao_ligado',
        'botao_desligado',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Tipos de eventos mapeados do código do TRX-16
    public const TIPOS = [
        '0001' => ['tipo' => 'IGNICAO_ON',     'descricao' => 'Ignição Ligada'],
        '0002' => ['tipo' => 'IGNICAO_OFF',    'descricao' => 'Ignição Desligada'],
        '0004' => ['tipo' => 'BATERIA_BAIXA',  'descricao' => 'Bateria Baixa'],
        '0008' => ['tipo' => 'VIOLACAO',       'descricao' => 'Violação Detectada'],
        '0016' => ['tipo' => 'PANICO',         'descricao' => 'Botão de Pânico'],
        '0032' => ['tipo' => 'EXCESSO_VEL',    'descricao' => 'Excesso de Velocidade'],
        '0064' => ['tipo' => 'CERCA_ENTRADA',  'descricao' => 'Entrada em Cerca Eletrônica'],
        '0128' => ['tipo' => 'CERCA_SAIDA',    'descricao' => 'Saída de Cerca Eletrônica'],
    ];

    public function rastreador(): BelongsTo
    {
        return $this->belongsTo(Rastreador::class);
    }

    public function posicao(): BelongsTo
    {
        return $this->belongsTo(Posicao::class);
    }
}
