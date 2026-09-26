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
        if (! Schema::hasTable('crm_ia_plantillas_privilegios')) {
            Schema::create('crm_ia_plantillas_privilegios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->string('nombre');
                $table->string('slug');
                $table->string('descripcion')->nullable();
                $table->boolean('es_sistema')->default(false);

                // Privilegios de Consulta / Carta
                $table->boolean('permitir_menu')->default(true);
                $table->boolean('permitir_precios')->default(true);
                $table->boolean('permitir_alergenos')->default(true);

                // Privilegios Operativos / Mesas y Reservas
                $table->boolean('permitir_verificar_mesas')->default(true);
                $table->boolean('permitir_crear_reservas')->default(true);
                $table->integer('max_personas_reserva')->default(6);
                $table->boolean('permitir_cancelar_reservas')->default(false);

                // Privilegios Marketing y Clientes
                $table->boolean('permitir_promociones')->default(true);
                $table->boolean('permitir_puntos_vip')->default(false);

                // Reglas de la casa y directivas de comportamiento del prompt
                $table->text('directivas_sistema')->nullable();

                $table->timestamps();

                $table->unique(['slug', 'sucursal_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_ia_plantillas_privilegios');
    }
};
