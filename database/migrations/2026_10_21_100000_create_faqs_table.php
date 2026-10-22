<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-24, RF-63: la FAQ que edita la psicologa; la landing y el asistente leen la
 * misma. Las respuestas de pago y cancelacion no viven aqui: salen de RN-05 y RN-06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 200);
            $table->text('answer');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Las preguntas que la landing tenia fijas. Se crean una sola vez: desde
        // aqui son de la psicologa, y una semilla las restauraria en cada despliegue.
        $now = now();

        DB::table('faqs')->insert(array_map(fn (array $faq): array => $faq + ['created_at' => $now, 'updated_at' => $now], [
            ['position' => 1, 'question' => '¿Cómo es la primera cita?', 'answer' => 'Es una conversación para conocer qué te trae a consulta y acordar juntos el acompañamiento que mejor te sirve. No necesitas preparar nada.'],
            ['position' => 2, 'question' => '¿La terapia es confidencial?', 'answer' => 'Sí. Lo que compartes en sesión se registra cifrado y solo la psicóloga tiene acceso. Recepción únicamente ve los datos administrativos de tu cita.'],
            ['position' => 3, 'question' => '¿Atienden a adolescentes?', 'answer' => 'Sí, con el acompañamiento de madre, padre o tutor en la primera sesión. Para niñas y niños ofrecemos terapia infantil.'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
