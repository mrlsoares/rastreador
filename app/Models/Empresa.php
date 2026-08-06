<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'telefone',
        'status',
        'data_expiracao',
    ];

    protected $casts = [
        'data_expiracao' => 'date',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function rastreadores(): HasMany
    {
        return $this->hasMany(Rastreador::class);
    }

    public function esp32Dispositivos(): HasMany
    {
        return $this->hasMany(Esp32Dispositivo::class);
    }
}
