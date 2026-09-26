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
            $table->string('email_driver')->default('env')->after('email_remitente_correo'); // env, smtp, log
            $table->string('email_smtp_host')->nullable()->after('email_driver');
            $table->integer('email_smtp_port')->default(587)->nullable()->after('email_smtp_host');
            $table->string('email_smtp_username')->nullable()->after('email_smtp_port');
            $table->text('email_smtp_password')->nullable()->after('email_smtp_username');
            $table->string('email_smtp_encryption')->default('tls')->nullable()->after('email_smtp_password'); // tls, ssl, null
            $table->string('email_correo_pruebas')->nullable()->after('email_smtp_encryption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'email_driver',
                'email_smtp_host',
                'email_smtp_port',
                'email_smtp_username',
                'email_smtp_password',
                'email_smtp_encryption',
                'email_correo_pruebas',
            ]);
        });
    }
};
