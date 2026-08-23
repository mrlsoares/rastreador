<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'razao_social'   => $this->faker->company(),
            'nome_fantasia'  => $this->faker->companySuffix()
                ? $this->faker->company()
                : $this->faker->company(),
            'cnpj'           => $this->faker->numerify('##.###.###/####-##'),
            'telefone'       => $this->faker->numerify('(##) #####-####'),
            'status'         => 'ativo',
            'data_expiracao' => null,
        ];
    }

    /** Empresa inativa. */
    public function inativa(): static
    {
        return $this->state(fn () => ['status' => 'inativo']);
    }
}
