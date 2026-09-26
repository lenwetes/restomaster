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
        Schema::table('crm_configuraciones', function (Blueprint $table) {
            // Kill-Switch y Plantilla
            if (! Schema::hasColumn('crm_configuraciones', 'ia_activo')) {
                $table->boolean('ia_activo')->default(false)->after('winback_dias_inactividad');
            }
            if (! Schema::hasColumn('crm_configuraciones', 'ia_plantilla_privilegio_id')) {
                $table->foreignId('ia_plantilla_privilegio_id')
                    ->nullable()
                    ->after('ia_activo')
                    ->constrained('crm_ia_plantillas_privilegios')
                    ->nullOnDelete();
            }

            // Motor y API Key (cifrada)
            if (! Schema::hasColumn('crm_configuraciones', 'ia_proveedor')) {
                $table->string('ia_proveedor')->default('gemini')->after('ia_plantilla_privilegio_id');
            }
            if (! Schema::hasColumn('crm_configuraciones', 'ia_modelo')) {
                $table->string('ia_modelo')->default('gemini-2.5-flash')->after('ia_proveedor');
            }
            if (! Schema::hasColumn('crm_configuraciones', 'ia_api_key')) {
                $table->text('ia_api_key')->nullable()->after('ia_modelo');
            }

            // Cuotas y Mensajes de fuera de servicio
            if (! Schema::hasColumn('crm_configuraciones', 'ia_limite_mensajes_por_cliente_dia')) {
                $table->integer('ia_limite_mensajes_por_cliente_dia')->default(15)->after('ia_api_key');
            }
            if (! Schema::hasColumn('crm_configuraciones', 'ia_mensaje_apagado')) {
                $table->text('ia_mensaje_apagado')->nullable()->after('ia_limite_mensajes_por_cliente_dia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_configuraciones', function (Blueprint $table) {
            $table->dropForeign(['ia_plantilla_privilegio_id']);
            $table->dropColumn([
                'ia_activo',
                'ia_plantilla_privilegio_id',
                'ia_proveedor',
                'ia_modelo',
                'ia_api_key',
                'ia_limite_mensajes_por_cliente_dia',
                'ia_mensaje_apagado',
            ]);
        });
    }
};
