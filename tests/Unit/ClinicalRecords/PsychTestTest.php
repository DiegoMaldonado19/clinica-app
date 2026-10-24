<?php

declare(strict_types=1);

use App\ClinicalRecords\Domain\Event\PsychTestAssigned;
use App\ClinicalRecords\Domain\Event\PsychTestOverdue;
use App\ClinicalRecords\Domain\Event\PsychTestSubmitted;
use App\ClinicalRecords\Domain\PsychTest;
use App\ClinicalRecords\Domain\PsychTestAssignment;
use App\Shared\Infrastructure\Clock\FrozenClock;

/** @return list<int> respuestas validas que suman $total, de 3 en 3 desde el primer item */
function answersSumming(PsychTest $test, int $total): array
{
    $answers = [];

    foreach ($test->items() as $ignored) {
        $answers[] = $answer = min(3, $total);
        $total -= $answer;
    }

    return $answers;
}

function clockAt(string $utc): FrozenClock
{
    return new FrozenClock(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
}

function psychAssignment(string $due = '2026-10-27 23:59'): PsychTestAssignment
{
    return PsychTestAssignment::assign('a-1', 'p-1', PsychTest::GAD7, clockAt($due)->now(), 'psi-1', clockAt('2026-10-20 10:00'));
}

it('clasifica cada banda del PHQ-9 en sus limites', function (int $score, string $severity) {
    $result = PsychTest::PHQ9->score(answersSumming(PsychTest::PHQ9, $score));

    expect($result->score)->toBe($score)->and($result->severity)->toBe($severity);
})->with([
    [0, 'Mínima'], [4, 'Mínima'], [5, 'Leve'], [9, 'Leve'], [10, 'Moderada'], [14, 'Moderada'],
    [15, 'Moderadamente grave'], [19, 'Moderadamente grave'], [20, 'Grave'], [27, 'Grave'],
]);

it('clasifica cada banda del GAD-7 en sus limites', function (int $score, string $severity) {
    $result = PsychTest::GAD7->score(answersSumming(PsychTest::GAD7, $score));

    expect($result->score)->toBe($score)->and($result->severity)->toBe($severity);
})->with([
    [0, 'Mínima'], [4, 'Mínima'], [5, 'Leve'], [9, 'Leve'], [10, 'Moderada'], [14, 'Moderada'], [15, 'Grave'], [21, 'Grave'],
]);

it('pide revision urgente si el item 9 del PHQ-9 es mayor que 0, aunque el total sea minimo', function () {
    $onlyItem9 = [0, 0, 0, 0, 0, 0, 0, 0, 1];

    expect(PsychTest::PHQ9->score($onlyItem9)->urgent)->toBeTrue()
        ->and(PsychTest::PHQ9->score($onlyItem9)->severity)->toBe('Mínima')
        ->and(PsychTest::PHQ9->score(answersSumming(PsychTest::PHQ9, 24))->urgent)->toBeFalse()
        ->and(PsychTest::GAD7->score(answersSumming(PsychTest::GAD7, 21))->urgent)->toBeFalse();
});

it('rechaza respuestas incompletas, fuera de la escala o desordenadas', function (array $answers) {
    expect(fn () => PsychTest::GAD7->score($answers))->toThrow(DomainException::class, 'Responde todas');
})->with([
    'falta un item' => [[0, 1, 2, 3, 0, 1]],
    'fuera de la escala' => [[0, 1, 2, 3, 0, 1, 4]],
    'desordenadas' => [[1 => 0, 0 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]],
]);

it('exige una fecha limite futura', function () {
    expect(fn () => psychAssignment('2026-10-20 09:00'))->toThrow(DomainException::class, 'posterior a hoy');
});

it('se responde una sola vez y antes de la fecha limite (HU-13)', function () {
    $assignment = psychAssignment();
    $clock = clockAt('2026-10-21 09:00');

    $result = $assignment->submit(answersSumming(PsychTest::GAD7, 11), $clock);

    expect($result->severity)->toBe('Moderada')
        ->and(array_map(fn (object $event): string => $event::class, $assignment->releaseEvents()))
        ->toBe([PsychTestAssigned::class, PsychTestSubmitted::class])
        ->and(fn () => $assignment->submit(answersSumming(PsychTest::GAD7, 0), $clock))->toThrow(DomainException::class, 'ya fue respondida')
        ->and($assignment->expire(clockAt('2026-10-28 08:00')))->toBeFalse();
});

it('vencida no se responde y se marca una sola vez', function () {
    $assignment = psychAssignment();
    $assignment->releaseEvents();
    $late = clockAt('2026-10-28 08:00');

    expect(psychAssignment()->expire(clockAt('2026-10-27 23:00')))->toBeFalse()
        ->and(fn () => $assignment->submit(answersSumming(PsychTest::GAD7, 3), $late))->toThrow(DomainException::class, 'ya pasó')
        ->and($assignment->expire($late))->toBeTrue()
        ->and($assignment->expire($late))->toBeFalse()
        ->and($assignment->releaseEvents())->toHaveCount(1)->each->toBeInstanceOf(PsychTestOverdue::class);
});
