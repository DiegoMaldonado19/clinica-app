<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora y bloqueo de agenda (F-11). La bitacora es de solo insercion tambien
 * en la base: una bitacora que se puede editar no prueba nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->char('actor_user_id', 36)->nullable(); // NULL = el sistema
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->char('subject_id', 36)->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 3);

            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $name = 'trg_audit_log_no_'.strtolower($operation);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$name}
                BEFORE {$operation} ON audit_log
                FOR EACH ROW
                BEGIN
                  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora es de solo insercion';
                END
                SQL);
        }

        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('therapist_id', 36);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255);
            $table->char('created_by', 36);
            $table->timestamps();

            $table->foreign('therapist_id')->references('id')->on('therapists')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['therapist_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_log_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_log_no_update');
        Schema::dropIfExists('audit_log');
    }
};
