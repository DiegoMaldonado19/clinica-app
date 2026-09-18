<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agendamiento publico (F-06). El consentimiento guarda la version del texto
 * aceptado, no solo la marca: el texto de cada version vive en config/clinic.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('nit', 20)->nullable()->after('document_number');
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('patient_id', 36);
            $table->string('document_code', 40);
            $table->string('document_version', 20);
            $table->dateTime('accepted_at');
            $table->binary('ip_address', 16)->nullable();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->index(['patient_id', 'document_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('nit');
        });
    }
};
