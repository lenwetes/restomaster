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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('telefono')->index();
            $table->string('email')->nullable();
            $table->string('documento')->nullable();
            $table->string('tier')->default('regular'); // 'regular', 'gold', 'vip', 'black'
            $table->integer('puntos_fidelidad')->default(0);
            $table->decimal('total_gastado', 12, 2)->default(0);
            $table->integer('visitas_count')->default(0);
            $table->text('alergias')->nullable();
            $table->text('preferencias')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
