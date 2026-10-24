<x-filament-panels::page>
    <p style="font-size: .875rem; opacity: .75;">Tus respuestas son confidenciales: solo las ve tu psicóloga.</p>

    {{ $this->table }}

    {{-- RF-62: la línea de crisis a la vista, como en el asistente. --}}
    <p style="font-size: .875rem; opacity: .75;">
        Si estás en crisis o piensas en hacerte daño, llama ahora a la línea de crisis {{ config('clinic.crisis_line') }}.
    </p>
</x-filament-panels::page>
