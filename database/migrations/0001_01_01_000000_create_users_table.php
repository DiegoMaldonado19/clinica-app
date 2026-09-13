<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema del doc 06 §3.1. Se edita la migracion de serie en lugar de anadir un
 * `ALTER`: el patron expandir/contraer del doc 04 §5 protege despliegues en
 * marcha, y aqui todavia no hay nada desplegado. Un `BIGINT -> CHAR(36)` por
 * `ALTER` solo dejaria un esquema muerto en el historial.
 *
 * La clave foranea a `roles` se declara en la migracion de identidad: los
 * catalogos se crean despues que esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('role_id');
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->string('phone_e164', 20)->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('temp_password_expires_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role_id', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
