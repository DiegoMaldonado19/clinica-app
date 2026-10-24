<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-13 (F3-08). El instrumento es un enum del dominio, como `payments.kind`:
 * sus items y bandas son codigo, no datos editables. Las respuestas van
 * cifradas y son resultado clinico (doc 05 §4.3), asi que tienen las defensas
 * de la nota sellada: respondida no cambia y nada se borra (doc 06 §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psych_test_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('clinical_record_id', 36);
            $table->enum('test', ['PHQ-9', 'GAD-7']);
            $table->char('assigned_by', 36);
            $table->dateTime('due_at');
            // Cifradas en la aplicacion con CLINICAL_ENCRYPTION_KEY (doc 05 §2.3).
            $table->text('answers')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();

            $table->foreign('clinical_record_id')->references('id')->on('clinical_records')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_psych_test_assignments_immutable
            BEFORE UPDATE ON psych_test_assignments
            FOR EACH ROW
            BEGIN
              IF OLD.submitted_at IS NOT NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una prueba respondida no cambia (HU-13)';
              END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_psych_test_assignments_no_delete
            BEFORE DELETE ON psych_test_assignments
            FOR EACH ROW
            BEGIN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una prueba psicometrica no se borra (doc 06 §7)';
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_psych_test_assignments_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_psych_test_assignments_immutable');
        Schema::dropIfExists('psych_test_assignments');
    }
};
