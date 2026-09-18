<x-filament-panels::page>
    <div @unless ($sealed) wire:poll.20s="autosave" @endunless style="display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: start;">
        <div>
            {{ $this->form }}
        </div>

        <x-filament::section heading="Historial" description="Versiones selladas del expediente">
            @forelse ($this->history() as $note)
                <div style="border-bottom: 1px solid rgba(0,0,0,.08); padding: .75rem 0;">
                    <p style="font-size: .875rem; font-weight: 600;">
                        {{ $note->sealed_at->tz(config('clinic.timezone'))->translatedFormat('j \d\e F Y') }}
                        · Sellada · versión {{ $note->version }}
                    </p>
                    @if ($note->amendment_reason)
                        <p style="font-size: .75rem; opacity: .7;">Enmienda: {{ $note->amendment_reason }}</p>
                    @endif
                    @foreach (['S' => $note->soap_subjective, 'O' => $note->soap_objective, 'A' => $note->soap_assessment, 'P' => $note->soap_plan] as $letter => $text)
                        <p style="font-size: .875rem; margin-top: .25rem;"><strong>{{ $letter }}:</strong> {{ $text }}</p>
                    @endforeach
                </div>
            @empty
                <p style="font-size: .875rem; opacity: .7;">Todavía no hay notas selladas.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
