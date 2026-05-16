<?php
/*
 * Created At: 2026-05-12T12:39:23Z
 */

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
        Schema::create('telemetrias', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('velocidade', 8, 2)->default(0);
            $table->decimal('bateria', 5, 2)->nullable();
            $table->boolean('panic_button')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetrias');
    }
};
