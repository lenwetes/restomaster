<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nombre_contacto');
            $table->string('telefono_contacto', 30);
            $table->string('email_contacto', 120)->nullable();
            $table->date('fecha');
            $table->time('hora_llegada');
            $table->unsignedSmallInteger('duracion_min')->default(120);
            $table->unsignedSmallInteger('personas');
            $table->string('estado')->default('solicitada');
            $table->string('origen')->default('sistema');
            $table->text('notas')->nullable();
            $table->decimal('anticipo', 12, 2)->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_publico', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
