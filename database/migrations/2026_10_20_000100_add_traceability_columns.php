<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad: quién registró y quién modificó por última vez cada registro
 * (nombre del usuario, igual que appointments.created_by y blocks.created_by).
 * La fecha y hora salen de created_at / updated_at.
 */
return new class extends Migration
{
    /** Tablas que aún no tienen created_by. */
    private const SIN_AUTOR = ['specialties', 'doctors', 'resources', 'patients', 'clinical_forms', 'users', 'waitlist_entries', 'patient_messages'];

    /** Tablas que ya tienen created_by y solo necesitan updated_by. */
    private const CON_AUTOR = ['appointments', 'blocks', 'reservations'];

    public function up(): void
    {
        foreach (self::SIN_AUTOR as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->string('created_by')->nullable();
                $t->string('updated_by')->nullable();
            });
        }
        foreach (self::CON_AUTOR as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->string('updated_by')->nullable()->after('created_by');
            });
        }

        // Lo que vino de la base heredada queda marcado como importación.
        foreach (['patients', 'doctors', 'specialties'] as $tabla) {
            DB::table($tabla)->whereNotNull('legacy_id')->update(['created_by' => 'Importación SIGMA']);
        }
    }

    public function down(): void
    {
        foreach (self::SIN_AUTOR as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn(['created_by', 'updated_by']));
        }
        foreach (self::CON_AUTOR as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('updated_by'));
        }
    }
};
