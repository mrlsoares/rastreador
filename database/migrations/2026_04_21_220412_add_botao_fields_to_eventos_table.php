<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->unsignedTinyInteger('botao_ligado')->default(0)->after('codigo_raw');
            $table->unsignedTinyInteger('botao_desligado')->default(1)->after('botao_ligado');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['botao_ligado', 'botao_desligado']);
        });
    }
};
