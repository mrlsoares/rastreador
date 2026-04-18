<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rastreadores', function (Blueprint $table) {
            $table->boolean('ignicao')->default(false)->after('ativo');
            $table->boolean('em_panico')->default(false)->after('ignicao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rastreadores', function (Blueprint $table) {
            $table->dropColumn(['ignicao', 'em_panico']);
        });
    }
};
