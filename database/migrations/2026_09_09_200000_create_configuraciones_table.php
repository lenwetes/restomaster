<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('grupo')->index();
            $table->string('clave');
            $table->json('valor')->nullable();
            $table->timestamps();
            $table->unique(['grupo', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
