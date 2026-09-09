<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_mesa', function (Blueprint $table) {
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->primary(['reserva_id', 'mesa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_mesa');
    }
};