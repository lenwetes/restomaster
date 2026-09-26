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
        Schema::table('crm_ia_plantillas_privilegios', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_ia_plantillas_privilegios', 'tono_conducta')) {
                $table->string('tono_conducta', 50)->default('amable_calido')->after('descripcion');
            }
            if (! Schema::hasColumn('crm_ia_plantillas_privilegios', 'prompt_personalidad')) {
                $table->text('prompt_personalidad')->nullable()->after('directivas_sistema');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_ia_plantillas_privilegios', function (Blueprint $table) {
            $table->dropColumn(['tono_conducta', 'prompt_personalidad']);
        });
    }
};
