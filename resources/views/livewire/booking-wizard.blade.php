@php
    $steps = ['Servicio', 'Fecha y hora', 'Tus datos'];
    $legend = [
        'available' => ['Disponible', 'bg-primary text-white'],
        'few' => ['Pocos cupos', 'bg-white text-ink ring-2 ring-primary'],
        'full' => ['Sin cupo', 'bg-white text-muted line-through'],
        'unavailable' => ['No disponible', 'bg-white text-muted/50'],
    ];
@endphp

<div class="mx-auto max-w-3xl px-5 py-10">
    @if ($step < 4)
        <div class="mb-8 flex items-center justify-between">
            <h1 class="font-serif text-2xl font-semibold md:text-3xl">Agendar cita</h1>
            <ol class="flex items-center gap-2 text-sm text-muted" aria-label="Paso {{ $step }} de 3">
                @foreach ($steps as $i => $label)
                    <li class="flex items-center gap-1">
                        <span @class(['size-3 rounded-full', 'bg-primary' => $i < $step, 'bg-line' => $i >= $step])></span>
                        <span class="hidden sm:inline {{ $i + 1 === $step ? 'font-medium text-ink' : '' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if ($notice)
        <div role="status" class="mb-6 flex gap-3 rounded-card border border-info/40 bg-info/10 p-4 text-sm">
            <span aria-hidden="true" class="font-semibold text-info">i</span>
            <p>{{ $notice }}</p>
        </div>
    @endif

    {{-- A-01 · Servicio --}}
    @if ($step === 1)
        <h2 class="text-xl font-medium">¿Qué tipo de terapia necesitas?</h2>
        <p class="mt-1 text-sm text-muted">Puedes cambiarlo más adelante.</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($this->services as $service)
                <button type="button" wire:click="chooseService('{{ $service->id }}')"
                    @class(['min-h-11 rounded-card border bg-white p-5 text-left shadow-card hover:border-primary', 'border-2 border-primary' => $service->id === $serviceId, 'border-line' => $service->id !== $serviceId])>
                    <span class="block font-medium">{{ $service->name }}</span>
                    <span class="mt-1 block text-sm text-muted">{{ $service->duration_minutes }} minutos</span>
                    <span class="mt-3 block font-serif text-xl font-semibold">{{ $service->currentPrice()?->format() }}</span>
                </button>
            @endforeach
        </div>
        <p class="mt-6 rounded-card bg-sand/60 p-4 text-sm text-muted">
            Si no estás seguro, elige terapia individual. En la primera sesión definimos juntos el acompañamiento que mejor te sirve.
        </p>
    @endif

    {{-- A-02 · Calendario --}}
    @if ($step === 2 && $this->service)
        <div class="flex items-center justify-between rounded-card bg-sand/60 px-4 py-3 text-sm">
            <span>{{ $this->service->name }} · {{ $this->service->duration_minutes }} min</span>
            <span class="font-medium">{{ $this->service->currentPrice()?->format() }}</span>
        </div>

        <div class="mt-6 rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <button type="button" wire:click="changeMonth(-1)" class="inline-flex size-11 items-center justify-center rounded-btn hover:bg-sand" aria-label="Mes anterior">‹</button>
                <h2 class="text-sm font-medium tracking-wide uppercase">
                    {{ \Illuminate\Support\Carbon::parse($month.'-01')->locale('es')->translatedFormat('F Y') }}
                </h2>
                <button type="button" wire:click="changeMonth(1)" class="inline-flex size-11 items-center justify-center rounded-btn hover:bg-sand" aria-label="Mes siguiente">›</button>
            </div>

            <table class="mt-4 w-full text-center text-sm">
                <thead class="text-muted">
                    <tr>@foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $d)<th class="pb-2 font-normal">{{ $d }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($this->calendar as $week)
                        <tr>
                            @foreach ($week as $cell)
                                <td class="p-0.5">
                                    @if ($cell)
                                        @php $isToday = $cell['date'] === now($timezone)->toDateString(); @endphp
                                        <button type="button"
                                            wire:click="chooseDay('{{ $cell['date'] }}')"
                                            @disabled(in_array($cell['state'], ['full', 'unavailable']) && ! $isToday)
                                            title="{{ $isToday ? 'Para citas de hoy, llámanos' : $legend[$cell['state']][0] }}"
                                            @class(['size-11 rounded-full', $legend[$cell['state']][1], 'outline-2 outline-offset-2 outline-ink' => $cell['date'] === $day])>
                                            {{ $cell['day'] }}
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <ul class="mt-4 flex flex-wrap gap-4 text-xs text-muted">
                @foreach ($legend as [$label, $classes])
                    <li class="flex items-center gap-2"><span class="inline-flex size-4 rounded-full {{ $classes }}"></span>{{ $label }}</li>
                @endforeach
            </ul>
        </div>

        @if ($this->nearest)
            <h3 class="mt-6 text-sm font-medium">Los horarios más cercanos</h3>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($this->nearest as $option)
                    <button type="button" wire:click="chooseSlot({{ $option->startsAt->getTimestamp() }})" class="min-h-11 rounded-btn border border-primary bg-white px-4 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                        {{ \Illuminate\Support\Carbon::instance($option->startsAt)->tz($timezone)->locale('es')->translatedFormat('D j M · H:i') }}
                    </button>
                @endforeach
            </div>
        @elseif ($day)
            <h3 class="mt-6 text-sm font-medium tracking-wide uppercase">
                {{ \Illuminate\Support\Carbon::parse($day)->locale('es')->translatedFormat('l j \d\e F') }}
            </h3>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($this->times as $option)
                    <button type="button" wire:click="chooseSlot({{ $option->startsAt->getTimestamp() }})" class="min-h-11 rounded-btn border border-primary bg-white px-4 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                        {{ \Illuminate\Support\Carbon::instance($option->startsAt)->tz($timezone)->format('H:i') }}
                    </button>
                @empty
                    <p class="text-sm text-muted">No quedan horarios este día. Prueba con otra fecha.</p>
                @endforelse
            </div>
        @endif

        <button type="button" wire:click="back" class="mt-8 min-h-11 text-sm text-muted hover:text-ink">← Cambiar servicio</button>
    @endif

    {{-- A-03 · Datos y politica --}}
    @if ($step === 3 && $this->service && $chosen)
        <form wire:submit="submit" class="grid gap-8 md:grid-cols-5" novalidate>
            <fieldset class="space-y-5 md:col-span-3">
                <legend class="text-sm font-medium tracking-wide text-muted uppercase">Tus datos</legend>
                @foreach ([
                    ['name', 'Nombre completo', 'text', 'Como aparece en tu DPI', null],
                    ['email', 'Correo electrónico', 'email', 'nombre@correo.com', 'Aquí te enviaremos la confirmación'],
                    ['phone', 'Teléfono', 'tel', '0000-0000', null],
                    ['nit', 'NIT (o CF)', 'text', 'CF', 'Si no tienes NIT, escribe CF'],
                ] as [$field, $label, $type, $placeholder, $help])
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
                        <input id="{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}" placeholder="{{ $placeholder }}"
                            @class(['mt-1 h-12 w-full rounded-card border bg-white px-4', 'border-error' => $errors->has($field), 'border-line' => ! $errors->has($field)])>
                        @error($field)
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @else
                            @if ($help)<p class="mt-1 text-sm text-muted">{{ $help }}</p>@endif
                        @enderror
                    </div>
                @endforeach
            </fieldset>

            <div class="space-y-5 md:col-span-2">
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <h2 class="text-sm font-medium tracking-wide text-muted uppercase">Resumen</h2>
                    <p class="mt-3 font-medium">{{ $this->service->name }}</p>
                    <p class="text-sm">{{ ucfirst($chosen->locale('es')->translatedFormat('l j \d\e F · H:i')) }}</p>
                    <p class="mt-3 flex justify-between text-sm"><span class="text-muted">Tarifa</span><span class="font-medium">{{ $this->service->currentPrice()?->format() }}</span></p>
                    <p class="mt-3 text-xs text-muted">Este horario queda apartado mientras confirmamos tu solicitud.</p>
                </div>

                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <h2 class="text-sm font-medium tracking-wide text-muted uppercase">Política de cancelación</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($policy->cancellationTiers() as $tier)
                            <li class="flex items-start gap-2">
                                <span aria-hidden="true" @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-success' => $tier['percentage'] === 0, 'bg-warning' => $tier['percentage'] > 0 && $tier['percentage'] < 100, 'bg-error' => $tier['percentage'] === 100])></span>
                                <span>{{ $tier['when'] }} → <strong class="font-medium">{{ $tier['fee'] }}</strong></span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="accepted" class="mt-0.5 size-6 shrink-0 accent-primary">
                    <span>Acepto la política de cancelación y el tratamiento de mis datos personales.</span>
                </label>
                @error('accepted')<p class="text-sm text-error">{{ $message }}</p>@enderror

                <div class="flex items-center justify-between gap-3">
                    <button type="button" wire:click="back" class="min-h-11 text-sm text-muted hover:text-ink">← Cambiar horario</button>
                    <button type="submit" wire:loading.attr="disabled" class="min-h-12 rounded-btn bg-primary px-6 font-medium text-white hover:bg-primary/90 disabled:opacity-60">Confirmar solicitud</button>
                </div>
            </div>
        </form>
    @endif

    {{-- A-04 · Confirmacion --}}
    @if ($step === 4 && $this->service && $chosen)
        <div class="text-center">
            <div class="mx-auto flex size-18 items-center justify-center rounded-full bg-success/15 text-3xl text-success" aria-hidden="true">✓</div>
            <h1 class="mt-6 font-serif text-2xl font-semibold md:text-3xl">Recibimos tu solicitud</h1>
            <p class="mx-auto mt-3 max-w-prose text-muted">
                Te confirmaremos en menos de {{ $policy->approvalSlaHours() }} horas y luego te enviaremos el enlace para registrar tu pago.
            </p>
        </div>

        <div class="mx-auto mt-8 max-w-md rounded-card border border-line bg-white p-5 shadow-card">
            <h2 class="text-sm font-medium tracking-wide text-muted uppercase">Tu solicitud</h2>
            <p class="mt-3 font-medium">{{ $this->service->name }}</p>
            <p class="text-sm">{{ ucfirst($chosen->locale('es')->translatedFormat('l j \d\e F · H:i')) }}</p>
            <p class="mt-3 flex justify-between text-sm"><span class="text-muted">Tarifa</span><span class="font-medium">{{ $this->service->currentPrice()?->format() }}</span></p>
        </div>

        <ol class="mx-auto mt-8 max-w-md space-y-3 text-sm">
            <li><strong class="font-medium">1. Revisamos tu solicitud</strong> <span class="text-muted">· en menos de {{ $policy->approvalSlaHours() }} horas</span></li>
            <li><strong class="font-medium">2. Te enviamos el enlace de pago</strong> <span class="text-muted">· a tu correo electrónico</span></li>
            <li><strong class="font-medium">3. Tu cita queda confirmada</strong> <span class="text-muted">· al aprobarse el pago</span></li>
        </ol>
        <p class="mt-6 text-center text-sm text-muted">Enviamos una copia a {{ $email }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/portal') }}" class="inline-flex min-h-12 items-center rounded-btn bg-primary px-6 font-medium text-white hover:bg-primary/90">Ir a mi portal</a>
            <a href="{{ url('/') }}" class="inline-flex min-h-12 items-center rounded-btn border border-primary px-6 font-medium text-primary">Volver al inicio</a>
        </div>
    @endif
</div>
