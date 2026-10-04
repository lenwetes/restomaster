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
            $table->date('fecha_nacimiento')->nullable()->after('email');
            $table->string('password')->nullable()->after('fecha_nacimiento');
            $table->timestamp('email_verificado_at')->nullable()->after('password');
            $table->string('vip_estado', 30)->default('ninguno')->index()->after('tier');
            $table->timestamp('vip_elegible_at')->nullable()->after('vip_estado');
            $table->timestamp('vip_desde')->nullable()->after('vip_elegible_at');
            $table->foreignId('vip_aprobado_por')->nullable()->constrained('users')->nullOnDelete()->after('vip_desde');
        });

        Schema::create('vip_invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('canal', 20)->default('whatsapp'); // whatsapp, email, ambos
            $table->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expira_at')->index();
            $table->timestamp('usada_at')->nullable();
            $table->string('estado', 30)->default('vigente')->index(); // vigente, completada, expirada, cancelada
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_invitaciones');

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['vip_aprobado_por']);
            $table->dropIndex(['vip_estado']);
            $table->dropColumn([
                'fecha_nacimiento',
                'password',
                'email_verificado_at',
                'vip_estado',
                'vip_elegible_at',
                'vip_desde',
                'vip_aprobado_por',
            ]);
        });
    }
};
