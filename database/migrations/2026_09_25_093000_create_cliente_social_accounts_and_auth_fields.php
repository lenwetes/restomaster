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
        // 1. Ampliar campos de autenticación y feedback en clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('auth_token', 80)->nullable()->unique();
            $table->timestampTz('auth_token_expires_at')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('proveedor_auth', 30)->nullable(); // google, email_magic
            $table->decimal('rating_promedio', 3, 2)->nullable();
            $table->integer('encuestas_respondidas')->default(0);
        });

        // 2. Tabla de cuentas sociales vinculadas
        Schema::create('cliente_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_id', 255);
            $table->text('provider_token')->nullable();
            $table->text('provider_refresh_token')->nullable();
            $table->text('avatar_url')->nullable();
            $table->string('nombre_proveedor', 255)->nullable();
            $table->string('email_proveedor', 255)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id'], 'uniq_csa_provider_id');
            $table->index('cliente_id', 'idx_csa_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_social_accounts');

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'auth_token',
                'auth_token_expires_at',
                'avatar_url',
                'proveedor_auth',
                'rating_promedio',
                'encuestas_respondidas',
            ]);
        });
    }
};
