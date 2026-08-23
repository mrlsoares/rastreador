<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Cria as empresas fixas do ambiente (1 = Haufer, 2 = teste desenvolvimento).
 * Idempotente — casa por nome_fantasia; num banco vazio a Haufer recebe id=1.
 */
class EmpresasSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            ['nome_fantasia' => 'Haufer',                'razao_social' => 'Haufer'],
            ['nome_fantasia' => 'teste desenvolvimento', 'razao_social' => 'teste desenvolvimento'],
        ];

        foreach ($empresas as $e) {
            Empresa::firstOrCreate(
                ['nome_fantasia' => $e['nome_fantasia']],
                ['razao_social' => $e['razao_social'], 'status' => 'ativo'],
            );
        }
    }
}
