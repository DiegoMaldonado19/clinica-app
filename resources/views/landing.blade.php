<x-layouts.public>
    {{-- Hero (mockup L-01) --}}
    <section class="mx-auto grid max-w-[1200px] gap-10 px-5 py-14 md:grid-cols-2 md:items-center md:py-20">
        <div>
            <h1 class="font-serif text-[28px] leading-tight font-semibold md:text-[40px]">
                Acompañamiento psicológico profesional en Ciudad de Guatemala
            </h1>
            <p class="mt-5 max-w-prose text-lg text-muted">
                Terapia individual, de pareja, familiar e infantil. Agenda tu cita en línea, sin llamadas.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                <x-ui.button :href="route('booking')">Agendar una cita</x-ui.button>
                <a href="{{ route('booking') }}" class="inline-flex min-h-11 items-center font-medium text-primary hover:underline">Ver disponibilidad →</a>
            </div>
            <p class="mt-8 text-sm text-muted">Colegiada activa · Confidencialidad garantizada · Respuesta en {{ $policy->approvalSlaHours() }} h</p>
        </div>
        {{-- Se reemplaza el archivo, no el codigo: public/images/landing/psicologa.jpg, 4:3. --}}
        <img src="{{ asset('images/landing/psicologa.jpg') }}" alt="Psic. Andrea Morales" width="1200" height="900" fetchpriority="high"
            class="aspect-[4/3] w-full rounded-card object-cover shadow-card">
    </section>

    {{-- M1 · Perfil profesional (L-03) --}}
    <section class="bg-sand/50">
        <div class="mx-auto grid max-w-[1200px] gap-8 px-5 py-14 md:grid-cols-3">
            <div class="md:col-span-1">
                <h2 class="font-serif text-2xl font-semibold">Psic. Andrea Morales</h2>
                <p class="mt-2 text-muted">Psicóloga clínica · Colegiado No. 0000</p>
                <p class="text-sm text-muted">Colegio de Psicólogos de Guatemala</p>
            </div>
            <dl class="grid grid-cols-3 gap-4 md:col-span-2">
                @foreach ([['12 años', 'de experiencia'], ['+400', 'pacientes acompañados'], ['Enfoque', 'cognitivo-conductual']] as [$figure, $label])
                    <div class="rounded-card bg-white p-5 shadow-card">
                        <dt class="font-serif text-2xl font-semibold text-primary">{{ $figure }}</dt>
                        <dd class="mt-1 text-sm text-muted">{{ $label }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- La clinica (F3-10): clinica-1.jpg a clinica-3.jpg en public/images/landing, 4:3. --}}
    <section class="mx-auto max-w-[1200px] px-5 pt-14">
        <h2 class="font-serif text-2xl font-semibold md:text-3xl">La clínica</h2>
        <p class="mt-2 max-w-prose text-muted">Un espacio tranquilo y privado, pensado para que te sientas en confianza.</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-3">
            @foreach (range(1, 3) as $photo)
                <img src="{{ asset("images/landing/clinica-{$photo}.jpg") }}" alt="Instalaciones de la clínica, foto {{ $photo }} de 3" width="800" height="600" loading="lazy"
                    class="aspect-[4/3] w-full rounded-card object-cover shadow-card">
            @endforeach
        </div>
    </section>

    {{-- M2 · Servicios y tarifas --}}
    <section id="servicios" class="mx-auto max-w-[1200px] px-5 py-14">
        <h2 class="font-serif text-2xl font-semibold md:text-3xl">Servicios y tarifas</h2>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($services as $service)
                <article class="flex flex-col rounded-card border border-line bg-white p-6 shadow-card">
                    <h3 class="font-medium">{{ $service->name }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ $service->duration_minutes }} minutos</p>
                    <p class="mt-4 font-serif text-2xl font-semibold">{{ $service->currentPrice()?->format() }}</p>
                    <a href="{{ route('booking', ['servicio' => $service->id]) }}" class="mt-4 inline-flex min-h-11 items-center font-medium text-primary hover:underline">Agendar →</a>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Como agendar --}}
    <section class="bg-sand/50">
        <div class="mx-auto max-w-[1200px] px-5 py-14">
            <h2 class="font-serif text-2xl font-semibold md:text-3xl">Cómo agendar tu cita</h2>
            <ol class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['Elige tu servicio y horario', 'Ves la disponibilidad real, en línea.'],
                    ['Confirmamos en menos de '.$policy->approvalSlaHours().' h', 'Te avisamos por correo.'],
                    ['Registras tu pago', 'Y tu cita queda lista.'],
                ] as $i => [$title, $text])
                    <li class="rounded-card bg-white p-6 shadow-card">
                        <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary font-medium text-white">{{ $i + 1 }}</span>
                        <h3 class="mt-4 font-medium">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- M5 · Preguntas frecuentes --}}
    <section id="preguntas" class="mx-auto max-w-3xl px-5 py-14">
        <h2 class="font-serif text-2xl font-semibold md:text-3xl">Preguntas frecuentes</h2>
        <div class="mt-8 divide-y divide-line rounded-card border border-line bg-white">
            {{-- Las edita la psicologa en el panel; el asistente cita estas mismas. --}}
            @foreach ($faqs as $faq)
                <details class="p-5" @if ($loop->first) open @endif>
                    <summary class="flex min-h-11 cursor-pointer items-center font-medium">{{ $faq->question }}</summary>
                    <p class="mt-2 whitespace-pre-line text-muted">{{ $faq->answer }}</p>
                </details>
            @endforeach
            <details class="p-5">
                <summary class="flex min-h-11 cursor-pointer items-center font-medium">¿Cómo se realiza el pago?</summary>
                <p class="mt-2 text-muted">Cuando confirmemos tu solicitud te enviaremos el enlace para registrar tu transferencia desde tu portal. También puedes pagar en efectivo al llegar. El pago por transferencia debe estar aprobado a más tardar {{ $policy->paymentDeadlineHours() }} horas antes de tu cita.</p>
            </details>
            <details class="p-5">
                <summary class="flex min-h-11 cursor-pointer items-center font-medium">¿Cuál es la política de cancelación?</summary>
                <ul class="mt-2 space-y-1 text-muted">
                    @foreach ($policy->cancellationTiers() as $tier)
                        <li>{{ $tier['when'] }}: <strong class="font-medium text-ink">{{ $tier['fee'] }}</strong></li>
                    @endforeach
                </ul>
            </details>
        </div>
    </section>

    <livewire:chat-widget />
</x-layouts.public>
