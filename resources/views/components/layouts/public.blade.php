@props(['title' => null])
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('clinic.name') }}</title>
        <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-page font-sans text-[16px] text-ink antialiased">
        <header class="border-b border-line bg-page/95">
            <nav class="mx-auto flex max-w-[1200px] items-center justify-between gap-4 px-5 py-4">
                <a href="{{ url('/') }}" class="shrink-0">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('clinic.name') }}" width="354" height="120" class="h-9 w-auto sm:h-11">
                </a>
                <div class="flex items-center gap-2 text-sm">
                    <a href="{{ url('/#servicios') }}" class="hidden min-h-11 items-center px-3 text-muted hover:text-ink md:inline-flex">Servicios</a>
                    <a href="{{ url('/#preguntas') }}" class="hidden min-h-11 items-center px-3 text-muted hover:text-ink md:inline-flex">Preguntas</a>
                    <a href="{{ url('/portal') }}" class="inline-flex min-h-11 items-center px-3 text-muted hover:text-ink">Mi portal</a>
                    <x-ui.button :href="route('booking')" size="md">Agendar una cita</x-ui.button>
                </div>
            </nav>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-line bg-sand/60">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-2 px-5 py-8 text-sm text-muted md:flex-row md:justify-between">
                <p>{{ config('clinic.name') }} · {{ config('clinic.therapist') }}</p>
                <p>{{ config('clinic.address') }} · Tel. {{ config('clinic.phone') }} · {{ config('clinic.email') }}</p>
            </div>
        </footer>
    </body>
</html>
