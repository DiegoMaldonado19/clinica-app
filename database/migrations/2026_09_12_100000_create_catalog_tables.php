<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los doce catalogos del doc 06 §2. Todos tienen la misma forma, asi que se
 * crean con un solo bloque: doce migraciones identicas serian doce archivos
 * diciendo lo mismo.
 *
 * Sin columnas derivadas como `is_terminal` o `releases_slot`: eso son metodos
 * del enum del dominio, no datos. El catalogo existe para que las claves
 * foraneas apunten a algo estable.
 */
return new class extends Migration
{
    /** Longitud de `code` por catalogo; 40 salvo que se indique otra cosa. */
    private const TABLES = [
        'roles' => 40,
        'abilities' => 60,
        'appointment_statuses' => 40,
        'payment_statuses' => 40,
        'payment_methods' => 40,
        'banks' => 40,
        'notification_channels' => 40,
        'notification_statuses' => 40,
        'cancellation_reasons' => 40,
        'service_categories' => 40,
        'sexes' => 40,
        'document_types' => 40,
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name => $codeLength) {
            Schema::create($name, function (Blueprint $table) use ($codeLength) {
                $table->id();
                $table->string('code', $codeLength)->unique();
                $table->string('label', 120);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::TABLES)) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
