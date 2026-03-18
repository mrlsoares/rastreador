<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rastreador_id')
                  ->constrained('rastreadores')
                  ->onDelete('cascade');
            $table->foreignId('posicao_id')
                  ->nullable()
                  ->constrained('posicoes')
                  ->onDelete('set null');
            $table->string('tipo', 50)->comment('Tipo do evento: IGNICAO_ON, IGNICAO_OFF, BATERIA_BAIXA, etc.');
            $table->string('descricao')->nullable();
            $table->string('codigo_raw', 20)->nullable()->comment('Código bruto do evento no protocolo');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rastreador_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
