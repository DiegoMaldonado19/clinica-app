<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente clinico (F-10), doc 06 §3.8. La inmutabilidad de la nota sellada
 * vive en tres capas; esta es la ultima: ni un UPDATE por SQL directo la cambia.
 * El trigger de DELETE no esta en el doc 06, pero §7 prohibe el borrado fisico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ficha administrativa: recepcion la crea y la ve (doc 05 §4.3).
        Schema::create('clinical_records', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('patient_id', 36)->unique();
            $table->text('reported_reason')->nullable();
            $table->string('referred_by', 150)->nullable();
            $table->dateTime('opened_at');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('clinical_record_id', 36);
            $table->char('appointment_id', 36)->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->char('supersedes_note_id', 36)->nullable();
            $table->string('amendment_reason', 500)->nullable();
            // Cifrados en la aplicacion con CLINICAL_ENCRYPTION_KEY (doc 05 §2.3).
            $table->text('soap_subjective')->nullable();
            $table->text('soap_objective')->nullable();
            $table->text('soap_assessment')->nullable();
            $table->text('soap_plan')->nullable();
            $table->char('author_id', 36);
            $table->dateTime('sealed_at')->nullable();
            $table->timestamps();

            $table->foreign('clinical_record_id')->references('id')->on('clinical_records')->restrictOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('supersedes_note_id')->references('id')->on('clinical_notes')->restrictOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['clinical_record_id', 'sealed_at']);
            $table->unique(['appointment_id', 'version']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_clinical_notes_immutable
            BEFORE UPDATE ON clinical_notes
            FOR EACH ROW
            BEGIN
              IF OLD.sealed_at IS NOT NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una nota sellada es inmutable (RN-11)';
              END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_clinical_notes_no_delete
            BEFORE DELETE ON clinical_notes
            FOR EACH ROW
            BEGIN
              SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una nota clinica no se borra (doc 06 §7)';
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_clinical_notes_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_clinical_notes_immutable');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('clinical_records');
    }
};
