{{-- C-01 · La línea de crisis queda fija en el pie: no depende de que el clasificador acierte (RF-62). --}}
<div x-data="{ open: false }" class="fixed right-4 bottom-4 z-50 flex flex-col items-end gap-3">
    <section x-show="open" style="display: none" aria-label="Asistente de la clínica"
        @class(['flex w-[min(400px,calc(100vw-2rem))] flex-col overflow-hidden rounded-card border border-line bg-page shadow-float', 'h-[min(680px,calc(100vh-6rem))]' => config('services.chatbot.enabled')])>
        <header class="flex items-center justify-between bg-primary px-4 py-3 text-white">
            <p class="font-medium">Asistente</p>
            <button type="button" x-on:click="open = false" class="inline-flex size-11 items-center justify-center rounded-btn hover:bg-white/10" aria-label="Cerrar el asistente">✕</button>
        </header>

        @if (config('services.chatbot.enabled'))
        <p class="bg-sand px-4 py-2 text-xs leading-relaxed text-ink">
            Soy un asistente automatizado y no sustituyo la atención profesional. Tus mensajes los procesa un proveedor de inteligencia artificial.
        </p>

        <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" aria-live="polite"
            x-init="$wire.$watch('messages', () => $nextTick(() => $el.scrollTop = $el.scrollHeight))">
            <p class="max-w-[85%] rounded-card border border-line bg-white px-4 py-3 text-sm">
                Hola. Puedo resolver dudas sobre servicios, tarifas y horarios, y ayudarte a agendar una cita. ¿En qué te ayudo?
            </p>

            @foreach ($messages as $message)
                @if ($message['role'] === 'user')
                    <p class="ml-auto max-w-[85%] rounded-card bg-primary px-4 py-3 text-sm text-white">{{ $message['content'] }}</p>
                @elseif ($message['role'] === 'notice')
                    <x-ui.notice tone="warning">{{ $message['content'] }}</x-ui.notice>
                @else
                    <div class="max-w-[85%] rounded-card border border-line bg-white px-4 py-3 text-sm">
                        <p class="whitespace-pre-line">{{ $message['content'] }}</p>
                        @foreach ($message['sources'] ?? [] as $source)
                            <span class="mt-2 mr-1 inline-block rounded-full bg-sand px-2 py-0.5 text-xs text-muted">Fuente: {{ $source }}</span>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($booking)
                <form wire:submit="book" class="space-y-3 rounded-card border border-primary/30 bg-white p-4 text-sm" novalidate>
                    <p class="font-medium">{{ $booking['service'] }} · {{ $this->bookingLabel() }}</p>

                    @foreach ([['name', 'Nombre completo', 'text'], ['email', 'Correo electrónico', 'email'], ['phone', 'Teléfono', 'tel'], ['nit', 'NIT (o CF)', 'text']] as [$field, $label, $type])
                        <x-ui.input :name="$field" :label="$label" :type="$type" id="chat-{{ $field }}" size="md" wire:model="{{ $field }}" />
                    @endforeach

                    <div class="rounded-btn bg-page p-3 text-xs">
                        <p class="font-medium">Política de cancelación</p>
                        <ul class="mt-1 space-y-1 text-muted">
                            @foreach ($policy->cancellationTiers() as $tier)
                                <li>{{ $tier['when'] }}: <strong class="font-medium text-ink">{{ $tier['fee'] }}</strong></li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-muted">{{ $privacy }}</p>
                    </div>

                    <label class="flex items-start gap-2 text-xs">
                        <input type="checkbox" wire:model="accepted" class="mt-0.5 size-5 shrink-0 accent-primary">
                        <span>Acepto la política de cancelación y el tratamiento de mis datos personales.</span>
                    </label>
                    @error('accepted')<p class="text-xs text-error">{{ $message }}</p>@enderror

                    <div class="flex items-center justify-between gap-2">
                        <button type="button" wire:click="cancelBooking" class="min-h-11 text-muted hover:text-ink">Elegir otro horario</button>
                        <x-ui.button type="submit" size="md" wire:loading.attr="disabled">Confirmar solicitud</x-ui.button>
                    </div>
                </form>
            @endif

            <p wire:loading wire:target="send,book" class="text-sm text-muted">Escribiendo…</p>
        </div>

        @if ($escalated)
            <p class="border-t border-error/30 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                Llama ahora a la línea de crisis: <a href="tel:{{ preg_replace('/[^\d+]/', '', config('clinic.crisis_line')) }}" class="underline">{{ config('clinic.crisis_line') }}</a>
            </p>
        @elseif ($closed)
            <p class="border-t border-line bg-white px-4 py-3 text-sm text-muted">Esta conversación terminó. Puedes agendar desde el sitio o llamar al {{ config('clinic.phone') }}.</p>
        @else
            <form wire:submit="send" class="border-t border-line bg-white p-3">
                <div class="flex items-center gap-2">
                    <label for="chat-draft" class="sr-only">Tu mensaje</label>
                    <input id="chat-draft" type="text" wire:model="draft" maxlength="1000" autocomplete="off" placeholder="Escribe tu mensaje"
                        class="h-11 min-w-0 flex-1 rounded-full border border-line bg-page px-4 text-sm">
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary/90 disabled:opacity-60" aria-label="Enviar">➤</button>
                </div>
                @error('draft')<p class="mt-1 text-xs text-error">{{ $message }}</p>@enderror
            </form>
        @endif
        @else
            <p class="px-4 py-4 text-sm">
                El asistente no está disponible por ahora. Revisa las
                <a href="#preguntas" x-on:click="open = false" class="font-medium underline">preguntas frecuentes</a>,
                <a href="{{ route('booking') }}" class="font-medium underline">agenda una cita</a>
                o llámanos al {{ config('clinic.phone') }}.
            </p>
        @endif

        <footer class="border-t border-line bg-page px-4 py-2 text-xs text-ink">
            ¿Necesitas ayuda urgente? Línea de crisis 24/7 ·
            <a href="tel:{{ preg_replace('/[^\d+]/', '', config('clinic.crisis_line')) }}" class="font-medium underline">{{ config('clinic.crisis_line') }}</a>
        </footer>
    </section>

    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
        class="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-5 font-medium text-white shadow-float hover:bg-primary/90">
        ¿Dudas? Escríbenos
    </button>
</div>
