<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Médicos y especialidades importados de la base heredada de SIGMA
 * guardan su identificador original (usuarios.id_u / especialidades.id_es).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['doctors', 'specialties'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['doctors', 'specialties'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->dropUnique(['legacy_id']);
                $t->dropColumn('legacy_id');
            });
        }
    }
};
