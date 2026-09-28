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
        // 1. Tabla de Promociones Comerciales de RestoMaster
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('titulo', 200);
            $table->string('slug', 220)->unique();
            $table->string('subtitulo', 255)->nullable();
            $table->text('descripcion');
            $table->text('terminos_condiciones')->nullable();
            $table->string('tipo_beneficio', 50)->default('descuento_porcentaje'); // descuento_porcentaje, precio_fijo, dos_por_uno, combo_especial, cortesia
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->decimal('precio_promocional', 12, 2)->nullable();
            $table->decimal('precio_original', 12, 2)->nullable();
            $table->string('imagen_url', 500)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->json('dias_semana')->nullable(); // ['lunes', 'martes', ...]
            $table->boolean('aplica_salon')->default(true);
            $table->boolean('aplica_delivery')->default(true);
            $table->boolean('mostrar_en_portada')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->integer('cupo_maximo')->nullable();
            $table->integer('veces_canjeada')->default(0);
            $table->integer('total_notificados_whatsapp')->default(0);
            $table->integer('total_notificados_email')->default(0);
            $table->timestampTz('ultimo_lanzamiento_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'mostrar_en_portada'], 'idx_promociones_activo_portada');
            $table->index(['fecha_inicio', 'fecha_fin'], 'idx_promociones_fechas');
            $table->index('slug', 'idx_promociones_slug');
        });

        // 2. Historial de Canjes / Redenciones por Cliente y Pedido
        Schema::create('promocion_canjes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->string('canal', 30)->default('salon'); // salon, delivery, web
            $table->string('codigo_cupon', 50)->nullable();
            $table->decimal('monto_descuento', 12, 2)->default(0);
            $table->timestampTz('canjeado_at');
            $table->timestamps();

            $table->index(['promocion_id', 'cliente_id'], 'idx_canje_promo_cliente');
            $table->index('canjeado_at', 'idx_canje_fecha');
        });

        // 3. Bitácora de Difusiones y Lanzamientos Masivos (WhatsApp / Email)
        Schema::create('promocion_difusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('canal', 30)->default('ambos'); // whatsapp, email, ambos
            $table->string('segmento', 50)->default('todos'); // todos, vip, inactivos
            $table->integer('total_destinatarios')->default(0);
            $table->integer('total_exitosos')->default(0);
            $table->integer('total_fallidos')->default(0);
            $table->string('estado', 30)->default('pendiente'); // pendiente, en_proceso, completado, fallido
            $table->json('detalles')->nullable();
            $table->timestampTz('iniciado_at');
            $table->timestampTz('completado_at')->nullable();
            $table->timestamps();

            $table->index(['promocion_id', 'estado'], 'idx_difusion_promo_estado');
            $table->index('iniciado_at', 'idx_difusion_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion_difusiones');
        Schema::dropIfExists('promocion_canjes');
        Schema::dropIfExists('promociones');
    }
};
