<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rastreador_id')
                  ->constrained('rastreadores')
                  ->onDelete('cascade');
            $table->timestamp('data_hora')->comment('Data/hora da posição enviada pelo dispositivo');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('velocidade')->default(0)->comment('Velocidade em km/h');
            $table->unsignedSmallInteger('angulo')->default(0)->comment('Direção em graus (0-360)');
            $table->unsignedTinyInteger('sinal_gps')->default(0)->comment('Qualidade do sinal GPS (0-9)');
            $table->string('raw_data')->nullable()->comment('Dados brutos recebidos via socket');
            $table->boolean('ignorada')->default(false)->comment('Posição inválida/duplicada');
            $table->timestamp('created_at')->useCurrent();

            // Índices para performance nas queries com filtro de data
            $table->index(['rastreador_id', 'data_hora']);
            $table->index('data_hora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posicoes');
    }
};
