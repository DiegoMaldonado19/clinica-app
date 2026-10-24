<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain;

use DomainException;

/**
 * Los instrumentos de HU-13 (DP-02), en la version oficial en espanol para
 * EE. UU. de Pfizer: «No se requiere permiso para reproducir, traducir,
 * presentar o distribuir». Se puntuan por suma de items.
 */
enum PsychTest: string
{
    case PHQ9 = 'PHQ-9';
    case GAD7 = 'GAD-7';

    public const QUESTION = 'Durante las últimas 2 semanas, ¿qué tan seguido ha tenido molestias debido a los siguientes problemas?';

    /** La posicion de cada opcion es su puntuacion. */
    public const OPTIONS = ['Ningún día', 'Varios días', 'Más de la mitad de los días', 'Casi todos los días'];

    /** PHQ-9, item 9: pensamientos de muerte o de hacerse dano. */
    private const SELF_HARM_ITEM = 8;

    public function label(): string
    {
        return match ($this) {
            self::PHQ9 => 'PHQ-9 · Cuestionario sobre la salud del paciente',
            self::GAD7 => 'GAD-7 · Escala de ansiedad generalizada',
        };
    }

    /** @return list<string> */
    public function items(): array
    {
        return match ($this) {
            self::PHQ9 => [
                'Poco interés o placer en hacer cosas',
                'Se ha sentido decaído(a), deprimido(a) o sin esperanzas',
                'Ha tenido dificultad para quedarse o permanecer dormido(a), o ha dormido demasiado',
                'Se ha sentido cansado(a) o con poca energía',
                'Sin apetito o ha comido en exceso',
                'Se ha sentido mal con usted mismo(a) — o que es un fracaso o que ha quedado mal con usted mismo(a) o con su familia',
                'Ha tenido dificultad para concentrarse en ciertas actividades, tales como leer el periódico o ver la televisión',
                '¿Se ha movido o hablado tan lento que otras personas podrían haberlo notado? o lo contrario — muy inquieto(a) o agitado(a) que ha estado moviéndose mucho más de lo normal',
                'Pensamientos de que estaría mejor muerto(a) o de lastimarse de alguna manera',
            ],
            self::GAD7 => [
                'Se ha sentido nervioso(a), ansioso(a) o con los nervios de punta',
                'No ha sido capaz de parar o controlar su preocupación',
                'Se ha preocupado demasiado por motivos diferentes',
                'Ha tenido dificultad para relajarse',
                'Se ha sentido tan inquieto(a) que no ha podido quedarse quieto(a)',
                'Se ha molestado o irritado fácilmente',
                'Ha tenido miedo de que algo terrible fuera a pasar',
            ],
        };
    }

    public function maxScore(): int
    {
        return count($this->items()) * (count(self::OPTIONS) - 1);
    }

    /** @param array<int, int> $answers una puntuacion por item, en orden */
    public function score(array $answers): TestResult
    {
        $valid = array_keys(self::OPTIONS);

        if (! array_is_list($answers) || count($answers) !== count($this->items()) || array_diff($answers, $valid) !== []) {
            throw new DomainException('Responde todas las preguntas.');
        }

        $score = array_sum($answers);

        return new TestResult($score, $this->severity($score), $this === self::PHQ9 && $answers[self::SELF_HARM_ITEM] > 0);
    }

    /** Bandas de Kroenke et al. (2001) y Spitzer et al. (2006): puntuacion minima de cada una. */
    private function severity(int $score): string
    {
        $bands = match ($this) {
            self::PHQ9 => [0 => 'Mínima', 5 => 'Leve', 10 => 'Moderada', 15 => 'Moderadamente grave', 20 => 'Grave'],
            self::GAD7 => [0 => 'Mínima', 5 => 'Leve', 10 => 'Moderada', 15 => 'Grave'],
        };

        return $bands[max(array_filter(array_keys($bands), fn (int $from): bool => $score >= $from))];
    }
}
