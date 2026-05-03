<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela mestre para os dispositivos ESP32
        Schema::create('esp32_dispositivos', function (Blueprint $table) {
            $table->id();
            $table->string('identificador')->unique()->comment('MAC Address ou ID Único do chip');
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_contato')->nullable();
            $table->timestamps();
        });

        // Tabela de telemetria (Time-series style)
        Schema::create('esp32_telemetrias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esp32_dispositivo_id')
                  ->constrained('esp32_dispositivos')
                  ->onDelete('cascade');
            
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('bateria_vcc', 5, 2)->nullable()->comment('Voltagem da bateria');
            $table->decimal('temperatura', 5, 2)->nullable();
            $table->unsignedSmallInteger('velocidade')->default(0);
            
            $table->json('payload_extra')->nullable()->comment('Dados adicionais em formato JSON');
            $table->timestamp('data_hora');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['esp32_dispositivo_id', 'data_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esp32_telemetrias');
        Schema::dropIfExists('esp32_dispositivos');
    }
};
