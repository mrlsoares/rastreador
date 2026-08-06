<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_dispositivos', function (Blueprint $table) {
            // Hash SHA-256 do token de ingestão (padrão Sanctum). Único e consultável.
            $table->string('api_token', 64)->nullable()->unique()->after('empresa_id');
            $table->string('token_last4', 4)->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('esp32_dispositivos', function (Blueprint $table) {
            $table->dropColumn(['api_token', 'token_last4']);
        });
    }
};
