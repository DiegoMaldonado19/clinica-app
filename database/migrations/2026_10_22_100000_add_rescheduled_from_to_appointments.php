<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** RF-28 · to-be-04: la cita nueva recuerda de cuál viene. Aditiva: las citas existentes quedan en NULL. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->char('rescheduled_from_id', 36)->nullable()->after('source');
            $table->foreign('rescheduled_from_id')->references('id')->on('appointments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_from_id']);
            $table->dropColumn('rescheduled_from_id');
        });
    }
};
