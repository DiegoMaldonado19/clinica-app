<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los parametros de RN-01 a RN-20 (doc 06 §3.10). Cambiar el SLA o los tramos
 * de cancelacion no requiere despliegue, y queda auditado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_rule_settings', function (Blueprint $table) {
            $table->string('rule_code', 20);
            $table->string('param_key', 60);
            $table->dateTime('effective_from');
            $table->string('param_value', 500);
            $table->enum('value_type', ['int', 'decimal', 'bool', 'json', 'duration']);
            // La clave foranea a `users` se anade en F-03: hoy `users.id` todavia
            // es BIGINT y no CHAR(36).
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->primary(['rule_code', 'param_key', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rule_settings');
    }
};
