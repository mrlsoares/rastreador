<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj')->nullable()->unique();
            $table->string('telefone')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->date('data_expiracao')->nullable();
            $table->timestamps();
        });

        // Empresas base: id=1 é a empresa padrão (dono default da ingestão/backfill).
        $now = now();
        DB::table('empresas')->insert([
            [
                'id' => 1, 'razao_social' => 'Haufer', 'nome_fantasia' => 'Haufer',
                'status' => 'ativo', 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'razao_social' => 'Teste Desenvolvimento', 'nome_fantasia' => 'teste desenvolvimento',
                'status' => 'ativo', 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // pgsql: ressincroniza a sequência do id após inserts explícitos.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('empresas', 'id'), (SELECT MAX(id) FROM empresas))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
