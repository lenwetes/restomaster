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
        Schema::create('impresoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_conexion')->default('red_ip'); // 'red_ip', 'usb_compartida', 'virtual_simulador'
            $table->string('ip_address')->nullable();
            $table->integer('puerto')->default(9100);
            $table->string('area')->default('todas'); // 'cocina_sushi', 'cocina_calientes', 'barra', 'caja_principal', 'todas'
            $table->integer('ancho_columnas')->default(48); // 48 cols para 80mm térmico, 32 cols para 58mm
            $table->integer('copias')->default(1);
            $table->boolean('activa')->default(true);
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impresoras');
    }
};
