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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono')->nullable()->change();

            if (! Schema::hasColumn('clientes', 'acepta_tratamiento_datos')) {
                $table->boolean('acepta_tratamiento_datos')->default(false);
            }
            if (! Schema::hasColumn('clientes', 'fecha_autorizacion_datos')) {
                $table->timestamp('fecha_autorizacion_datos')->nullable();
            }
            if (! Schema::hasColumn('clientes', 'canal_autorizacion_datos')) {
                $table->string('canal_autorizacion_datos', 50)->nullable();
            }
            if (! Schema::hasColumn('clientes', 'autoriza_whatsapp')) {
                $table->boolean('autoriza_whatsapp')->default(false);
            }
            if (! Schema::hasColumn('clientes', 'autoriza_email')) {
                $table->boolean('autoriza_email')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono')->nullable(false)->change();

            $table->dropColumn([
                'acepta_tratamiento_datos',
                'fecha_autorizacion_datos',
                'canal_autorizacion_datos',
                'autoriza_whatsapp',
                'autoriza_email',
            ]);
        });
    }
};
