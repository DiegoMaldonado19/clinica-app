<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los canales que eligió cada paciente. Sin filas, el correo. Que un canal esté
 * activo lo decide la configuración, no `notification_channels.is_active`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->char('user_id', 36);
            $table->unsignedBigInteger('channel_id');
            $table->timestamps();

            $table->primary(['user_id', 'channel_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('channel_id')->references('id')->on('notification_channels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
