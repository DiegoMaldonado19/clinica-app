<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de notificaciones (F-04). `notification_preferences` no se crea: con un
 * solo canal activo no hay preferencia que guardar (declarado en docs/fase-2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            // RN-17: (evento, destinatario, canal) resumidos en un hash unico.
            $table->char('idempotency_key', 64)->unique();
            $table->string('template_code', 10);
            $table->unsignedBigInteger('channel_id');
            $table->char('recipient_id', 36);
            $table->unsignedBigInteger('status_id');
            $table->unsignedTinyInteger('attempts')->default(0);
            // Cifrado: puede llevar la credencial temporal de NT-02.
            $table->text('payload');
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->foreign('template_code')->references('code')->on('notification_templates')->restrictOnDelete();
            $table->foreign('channel_id')->references('id')->on('notification_channels')->restrictOnDelete();
            $table->foreign('recipient_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('status_id')->references('id')->on('notification_statuses')->restrictOnDelete();
            $table->index(['status_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notification_templates');
    }
};
