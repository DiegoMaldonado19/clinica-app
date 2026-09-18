<x-filament-panels::page>
    @if ($pending = $this->pendingPayment())
        @php
            $minutes = max(0, (int) now()->diffInMinutes($pending->payment_due_at, false));
        @endphp
        <x-filament::section icon="heroicon-o-clock" icon-color="warning" heading="Tienes 1 pago pendiente">
            <p style="font-weight: 600;">{{ $pending->service->name }}</p>
            <p>{{ ucfirst($pending->starts_at->tz(config('clinic.timezone'))->locale('es')->translatedFormat('l j \d\e F · H:i')) }} · {{ \App\Shared\Domain\ValueObject\Money::gtq($pending->fee_amount_cents)->format() }}</p>
            <p style="margin-top: .5rem;">
                <strong>Quedan {{ intdiv($minutes, 60) }} h {{ $minutes % 60 }} min para registrar tu pago.</strong>
                Después de ese tiempo el horario se libera.
            </p>
            <p style="font-size: .875rem; opacity: .75;">Usa «Registrar mi pago» en la cita, o elige pagar en efectivo al llegar.</p>
        </x-filament::section>
    @endif

    @if ($credit = $this->creditBalance())
        <x-filament::section icon="heroicon-o-banknotes" icon-color="success" heading="Tienes {{ $credit }} de crédito a favor">
            <p style="font-size: .875rem;">Se aplicará a tu próxima cita.</p>
        </x-filament::section>
    @endif

    {{ $this->table }}

    <p style="font-size: .875rem; opacity: .75;">
        @foreach ($this->policy()->cancellationTiers() as $tier)
            {{ $tier['when'] }}: {{ mb_strtolower($tier['fee']) }}.
        @endforeach
    </p>
</x-filament-panels::page>
