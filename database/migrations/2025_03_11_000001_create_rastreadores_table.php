<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cria o schema 'rastreador' se não existir
        DB::statement('CREATE SCHEMA IF NOT EXISTS rastreador');

        Schema::create('rastreadores', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 20)->unique()->comment('IMEI do dispositivo TRX-16');
            $table->string('nome', 100)->comment('Nome/apelido do rastreador');
            $table->string('placa', 10)->nullable()->comment('Placa do veículo');
            $table->string('modelo_veiculo', 100)->nullable();
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_contato')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rastreadores');
    }
};
