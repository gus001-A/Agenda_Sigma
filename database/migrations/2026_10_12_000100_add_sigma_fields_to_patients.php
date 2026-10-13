<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para los pacientes importados de la base heredada de SIGMA:
 * el expediente original, CURP y sexo. La fecha de nacimiento y el
 * teléfono pasan a opcionales porque muchos expedientes antiguos no los tienen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $t) {
            $t->unsignedBigInteger('legacy_id')->nullable()->unique()->after('sigma_id');
            $t->string('curp', 18)->nullable()->after('name');
            $t->string('sex', 10)->nullable()->after('curp');
            $t->date('birth_date')->nullable()->change();
            $t->string('phone', 30)->nullable()->change();
            $t->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $t) {
            $t->dropIndex(['phone']);
            $t->dropUnique(['legacy_id']);
            $t->dropColumn(['legacy_id', 'curp', 'sex']);
        });
    }
};
