<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accion');
            $table->string('entidad');
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->json('datos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['entidad', 'entidad_id']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};