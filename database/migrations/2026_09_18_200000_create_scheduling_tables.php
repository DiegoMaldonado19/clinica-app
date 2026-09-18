<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling (F-05), doc 06 §3.3 y §3.5.
 *
 * Diferencia deliberada con el doc 06: el indice contra la doble reserva es
 * `(therapist_id, starts_at, active_slot)`. `active_slot` vale 1 mientras la
 * cita ocupa el horario y NULL cuando lo libera; MariaDB no compara NULL en un
 * UNIQUE, asi que un horario expirado o cancelado vuelve a poder reservarse.
 * Con el indice del doc 06 ese horario quedaria bloqueado para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('category_id');
            $table->string('name', 120);
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('service_categories')->restrictOnDelete();
        });

        // La tarifa vigente es la fila con `effective_from` mas reciente ya en
        // vigor; la cita congela la suya al solicitarse.
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->char('service_id', 36);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('GTQ');
            $table->dateTime('effective_from');
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->unique(['service_id', 'effective_from']);
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->char('therapist_id', 36);
            $table->unsignedTinyInteger('weekday'); // ISO-8601: 1 lunes ... 7 domingo
            $table->time('starts_at');              // hora local de la clinica
            $table->time('ends_at');
            $table->timestamps();

            $table->foreign('therapist_id')->references('id')->on('therapists')->restrictOnDelete();
            $table->index(['therapist_id', 'weekday']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('patient_id', 36);
            $table->char('therapist_id', 36);
            $table->char('service_id', 36);
            $table->unsignedBigInteger('status_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('fee_amount_cents');
            $table->char('fee_currency', 3)->default('GTQ');
            $table->dateTime('requested_at');
            $table->dateTime('hold_expires_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->char('approved_by', 36)->nullable();
            $table->dateTime('payment_due_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->enum('source', ['web', 'chat', 'phone', 'walk_in']);
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('therapist_id')->references('id')->on('therapists')->restrictOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->foreign('status_id')->references('id')->on('appointment_statuses')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->restrictOnDelete();

            $table->unique(['therapist_id', 'starts_at', 'active_slot'], 'appointments_slot_unique');
            $table->index(['status_id', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->index(['status_id', 'hold_expires_at']);
            $table->index(['status_id', 'payment_due_at']);
            $table->index(['source', 'starts_at']);
        });

        Schema::create('appointment_status_log', function (Blueprint $table) {
            $table->id();
            $table->char('appointment_id', 36);
            $table->unsignedBigInteger('from_status_id')->nullable();
            $table->unsignedBigInteger('to_status_id');
            $table->char('changed_by', 36)->nullable(); // NULL = el sistema
            $table->string('reason_text', 500)->nullable();
            $table->dateTime('occurred_at', 3);

            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('from_status_id')->references('id')->on('appointment_statuses')->restrictOnDelete();
            $table->foreign('to_status_id')->references('id')->on('appointment_statuses')->restrictOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['appointment_id', 'occurred_at']);
            $table->index(['to_status_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_log');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
    }
};
