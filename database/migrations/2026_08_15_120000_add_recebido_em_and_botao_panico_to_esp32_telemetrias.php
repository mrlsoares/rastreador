<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_telemetrias', function (Blueprint $table) {
            // Horário de recebimento no servidor — fonte de tempo confiável,
            // independente do relógio do dispositivo (que pode vir dessincronizado).
            $table->timestamp('recebido_em')->nullable()->after('data_hora')
                  ->comment('Horário de recebimento no servidor (fonte confiável)');

            // Estado do gatilho de pânico/abastecimento no momento do envio.
            // Promovido de payload_extra->botao_panico para coluna própria
            // (consultável/indexável), que é o dado central do projeto.
            $table->boolean('botao_panico')->default(false)->after('recebido_em')
                  ->comment('Gatilho de panico/abastecimento (true = acionado)');

            $table->index(['esp32_dispositivo_id', 'botao_panico']);
            $table->index(['esp32_dispositivo_id', 'recebido_em']);
        });
    }

    public function down(): void
    {
        Schema::table('esp32_telemetrias', function (Blueprint $table) {
            $table->dropIndex(['esp32_dispositivo_id', 'botao_panico']);
            $table->dropIndex(['esp32_dispositivo_id', 'recebido_em']);
            $table->dropColumn(['recebido_em', 'botao_panico']);
        });
    }
};
