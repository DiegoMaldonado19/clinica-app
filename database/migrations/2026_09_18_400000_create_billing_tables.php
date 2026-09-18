<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (F-08, F-09), doc 06 §3.6, §3.7 y §3.9. Nada financiero se borra: toda
 * clave foranea es RESTRICT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('appointment_id', 36);
            $table->unsignedBigInteger('method_id')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('GTQ');
            $table->enum('kind', ['SESSION_FEE', 'LATE_CANCELLATION_FEE', 'NO_SHOW_FEE']);
            $table->unsignedTinyInteger('fee_percentage')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->char('reviewed_by', 36)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->dateTime('waived_at')->nullable();
            $table->char('waived_by', 36)->nullable();
            $table->string('waiver_reason', 500)->nullable();
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->foreign('status_id')->references('id')->on('payment_statuses')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('waived_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status_id', 'submitted_at']);
            $table->index(['appointment_id', 'kind']);
        });

        // Un pago rechazado admite un comprobante nuevo: la relacion es 1-N en
        // la practica, aunque el ERD dibuje 1-0..1.
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('payment_id', 36);
            $table->string('receipt_number', 50);
            $table->unsignedBigInteger('origin_bank_id');
            $table->string('origin_account_mask', 30); // solo los ultimos 4 digitos
            $table->date('deposited_on');
            $table->string('file_path', 500);
            $table->char('file_hash_sha256', 64);
            $table->unsignedInteger('file_size_bytes');
            $table->dateTime('uploaded_at');

            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('origin_bank_id')->references('id')->on('banks')->restrictOnDelete();
            $table->unique(['origin_bank_id', 'receipt_number']); // RN-14
            $table->index('file_hash_sha256');
        });

        Schema::create('patient_credits', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('patient_id', 36);
            $table->char('origin_appointment_id', 36);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('GTQ');
            $table->char('consumed_by_appointment_id', 36)->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('consumed_at')->nullable();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('origin_appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('consumed_by_appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->index(['patient_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_credits');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payments');
    }
};
