<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema del Sistema Integral de Agenda, Preconsulta e Historias Clínicas (SIGMA24).
 * Las horas se guardan como texto HH:MM para simplificar el cálculo de espacios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        Schema::create('role_modules', function (Blueprint $t) {
            $t->id();
            $t->string('role', 30);
            $t->string('module', 40);
            $t->unique(['role', 'module']);
        });

        Schema::create('specialties', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->decimal('default_cost', 10, 2)->default(0);
            $t->unsignedBigInteger('first_form_id')->nullable();
            $t->unsignedBigInteger('sub_form_id')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('resources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type', 40);
            $t->string('location')->nullable();
            $t->unsignedInteger('capacity')->default(1);
            $t->char('opens_at', 5)->default('07:00');
            $t->char('closes_at', 5)->default('21:00');
            $t->decimal('cost', 10, 2)->default(0);
            $t->string('equipment')->nullable();
            $t->string('responsible')->nullable();
            $t->string('rules')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('doctors', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('specialty_id')->constrained();
            $t->string('phone', 30)->nullable();
            $t->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('cost', 10, 2)->default(0);
            $t->unsignedSmallInteger('duration')->default(30);
            $t->string('deposit_mode', 12)->default('monto');
            $t->decimal('deposit_value', 10, 2)->default(0);
            $t->json('days');
            $t->char('start_time', 5);
            $t->char('end_time', 5);
            $t->string('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->string('sigma_id', 20)->unique();
            $t->string('name');
            $t->date('birth_date');
            $t->string('phone', 30);
            $t->string('email')->nullable();
            $t->timestamps();
            $t->index('name');
        });

        Schema::create('clinical_forms', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $t->string('consult_type', 40);
            $t->boolean('active')->default(true);
            $t->json('draft')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_form_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_form_id')->constrained()->cascadeOnDelete();
            $t->string('version', 10);
            $t->string('notes')->nullable();
            $t->json('sections');
            $t->date('published_at');
            $t->string('published_by')->nullable();
            $t->timestamps();
            $t->unique(['clinical_form_id', 'version']);
        });

        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->string('token', 40)->unique();
            $t->foreignId('patient_id')->constrained();
            $t->foreignId('doctor_id')->constrained();
            $t->foreignId('specialty_id')->constrained();
            $t->date('date');
            $t->char('time', 5);
            $t->unsignedSmallInteger('duration');
            $t->string('type', 40);
            $t->string('reason')->nullable();
            $t->decimal('cost', 10, 2)->default(0);
            $t->decimal('deposit', 10, 2)->default(0);
            $t->decimal('paid', 10, 2)->default(0);
            $t->boolean('refunded')->default(false);
            $t->string('payment_method', 40)->nullable();
            $t->string('notes')->nullable();
            $t->string('channel', 30)->default('WhatsApp');
            $t->boolean('confirmed')->default(false);
            $t->string('status', 30)->default('Agendada');
            $t->foreignId('clinical_form_id')->nullable()->constrained()->nullOnDelete();
            $t->string('form_version', 10)->nullable();
            $t->string('hc_status', 30)->default('Pendiente');
            $t->json('hc_data')->nullable();
            $t->timestamp('hc_submitted_at')->nullable();
            $t->string('reviewed_by')->nullable();
            $t->text('attention_note')->nullable();
            $t->text('update_request')->nullable();
            $t->boolean('prev_asked')->default(false);
            $t->unsignedBigInteger('rescheduled_from_id')->nullable();
            $t->unsignedBigInteger('rescheduled_to_id')->nullable();
            $t->json('reminders_sent')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
            $t->index(['doctor_id', 'date']);
            $t->index(['date', 'status']);
        });

        Schema::create('appointment_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('method', 40);
            $t->string('reference')->nullable();
            $t->string('user_name')->nullable();
            $t->timestamp('paid_at');
            $t->timestamps();
        });

        Schema::create('appointment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->string('user_name')->nullable();
            $t->string('text', 500);
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('appointment_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained();
            $t->string('field_id', 40);
            $t->string('original_name');
            $t->string('path')->nullable();
            $t->string('mime', 100)->nullable();
            $t->unsignedInteger('size_kb')->default(0);
            $t->timestamps();
        });

        Schema::create('blocks', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 10); // doctor | resource
            $t->unsignedBigInteger('target_id')->nullable(); // null = todos los médicos
            $t->string('type', 40);
            $t->date('date');
            $t->char('start_time', 5);
            $t->char('end_time', 5);
            $t->boolean('recurring')->default(false);
            $t->json('days')->nullable();
            $t->date('until')->nullable();
            $t->string('notes')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
            $t->index(['scope', 'target_id']);
        });

        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('resource_id')->constrained();
            $t->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date');
            $t->char('start_time', 5);
            $t->unsignedSmallInteger('duration');
            $t->string('procedure');
            $t->string('notes')->nullable();
            $t->string('status', 20)->default('Programada');
            $t->string('created_by')->nullable();
            $t->timestamps();
            $t->index(['resource_id', 'date']);
        });

        Schema::create('waitlist_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained();
            $t->foreignId('specialty_id')->constrained();
            $t->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $t->date('requested_at');
            $t->string('preference', 30)->default('Cualquier horario');
            $t->string('priority', 10)->default('Media');
            $t->string('notes')->nullable();
            $t->string('status', 20)->default('En espera');
            $t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 30);
            $t->string('kind', 30);
            $t->text('body');
            $t->string('status', 20)->default('Enviado');
            $t->timestamp('sent_at');
            $t->timestamp('read_at')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
        });

        Schema::create('reminder_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique(); // r72, r24, r0
            $t->unsignedSmallInteger('hours');
            $t->boolean('active')->default(true);
            $t->string('text', 500);
            $t->boolean('only_if_hc_pending')->default(false);
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_name')->nullable();
            $t->string('action', 80);
            $t->text('detail')->nullable();
            $t->unsignedBigInteger('patient_id')->nullable()->index();
            $t->unsignedBigInteger('appointment_id')->nullable()->index();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reminder_rules', 'patient_messages', 'waitlist_entries', 'reservations', 'blocks',
            'appointment_files', 'appointment_events', 'appointment_payments', 'appointments', 'clinical_form_versions',
            'clinical_forms', 'patients', 'doctors', 'resources', 'specialties', 'role_modules', 'settings'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
