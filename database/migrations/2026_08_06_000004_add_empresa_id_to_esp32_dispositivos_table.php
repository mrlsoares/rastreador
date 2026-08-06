<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp32_dispositivos', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
            $table->index('empresa_id');
        });

        // Backfill: dispositivos ESP32 existentes passam para a empresa padrão (id=1).
        if (Schema::hasTable('empresas') && DB::table('empresas')->where('id', 1)->exists()) {
            DB::table('esp32_dispositivos')->whereNull('empresa_id')->update(['empresa_id' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('esp32_dispositivos', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
