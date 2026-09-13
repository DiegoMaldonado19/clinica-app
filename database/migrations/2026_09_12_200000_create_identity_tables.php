<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `patients` y `therapists` comparten la clave primaria con `users`: es la
 * relacion 1-0..1 del ERD del doc 06 §1. Toda clave foranea va con
 * `ON DELETE RESTRICT` (doc 06 §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->string('document_number', 30)->nullable();
            $table->unsignedBigInteger('sex_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone_e164', 20)->nullable();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('document_type_id')->references('id')->on('document_types')->restrictOnDelete();
            $table->foreign('sex_id')->references('id')->on('sexes')->restrictOnDelete();
            $table->unique(['document_type_id', 'document_number']);
        });

        Schema::create('therapists', function (Blueprint $table) {
            // Extiende `users` por la relacion 1-0..1 del ERD. Sus atributos
            // propios llegan con F-05, que es donde se usan.
            $table->char('id', 36)->primary();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('role_ability', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('ability_id');

            $table->primary(['role_id', 'ability_id']);
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('ability_id')->references('id')->on('abilities')->restrictOnDelete();
        });

        // La clave foranea que F-02 dejo pendiente: ya existe `users.id` CHAR(36).
        Schema::table('business_rule_settings', function (Blueprint $table) {
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_rule_settings', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('role_ability');
        Schema::dropIfExists('therapists');
        Schema::dropIfExists('patients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};
