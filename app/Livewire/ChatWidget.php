<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\BookingContact;
use App\Support\PolicyText;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * C-01 · HU-24/25/26. La conversación vive solo en esta página: Laravel no la
 * guarda y el asistente no tiene estado. Los datos de contacto viajan del
 * formulario a la API, nunca al modelo.
 */
final class ChatWidget extends Component
{
    #[Locked]
    public string $sessionId = '';

    /** @var list<array{role: string, content: string, sources?: list<string>}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public bool $closed = false;

    #[Locked]
    public bool $escalated = false;

    /** @var array{service_id: string, service: string, starts_at: string, date: string, time: string}|null */
    #[Locked]
    public ?array $booking = null;

    public string $draft = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $nit = '';

    public bool $accepted = false;

    public function mount(): void
    {
        $this->sessionId = (string) Str::uuid7();
    }

    public function send(): void
    {
        $text = trim($this->draft);

        if ($this->closed || $text === '') {
            return;
        }

        $this->validate(['draft' => ['max:1000']], ['draft.max' => 'Escribe un mensaje más corto, de hasta 1000 caracteres.']);

        // Doc 05 §6: 30 mensajes por minuto por IP.
        if (! RateLimiter::attempt('chat-ip:'.request()->ip(), 30, fn (): bool => true)) {
            $this->notice('Escribiste muchos mensajes seguidos. Espera un minuto, por favor.');

            return;
        }

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->draft = '';

        $reply = $this->call('chat', ['session_id' => $this->sessionId, 'messages' => $this->history()]);

        if ($reply === null) {
            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => (string) $reply['reply'], 'sources' => $reply['sources'] ?? []];
        $this->escalated = (bool) $reply['escalated'];
        $this->closed = (bool) $reply['closed'];
        $this->booking = $reply['action'] ?? $this->booking;
    }

    /** La persona confirma con sus datos y acepta la política; el asistente agenda por la API. */
    public function book(): void
    {
        if ($this->booking === null || $this->closed) {
            return;
        }

        $this->validate(BookingContact::rules(), BookingContact::messages());

        $result = $this->call('bookings', [
            'service_id' => $this->booking['service_id'],
            'starts_at' => $this->booking['starts_at'],
            'name' => trim($this->name),
            'email' => $this->email,
            'phone' => $this->phone,
            'nit' => $this->nit,
            'accepted' => $this->accepted,
            'client_ip' => request()->ip(),
        ]);

        if ($result !== null) {
            $this->messages[] = ['role' => 'assistant', 'content' => (string) $result['reply']];
            $this->reset('booking', 'accepted');
        }
    }

    public function cancelBooking(): void
    {
        $this->reset('booking', 'accepted');
    }

    public function bookingLabel(): string
    {
        return $this->booking === null ? '' : ucfirst(Carbon::parse($this->booking['starts_at'])
            ->tz(config('clinic.timezone'))->locale('es')->translatedFormat('l j \d\e F · H:i'));
    }

    public function render(PolicyText $policy): View
    {
        return view('livewire.chat-widget', [
            'policy' => $policy,
            'privacy' => config('clinic.consents.PRIVACIDAD.'.PolicyText::consentVersions()['PRIVACIDAD']),
        ]);
    }

    /** @return list<array{role: string, content: string}> lo que se le muestra al asistente: sin los avisos del widget */
    private function history(): array
    {
        return array_values(array_map(
            fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            array_filter($this->messages, fn (array $message): bool => $message['role'] !== 'notice'),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function call(string $path, array $payload): ?array
    {
        try {
            return Http::withToken((string) config('services.chatbot.token'))
                ->acceptJson()
                ->timeout(60)
                ->post(config('services.chatbot.url')."/{$path}", $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            Log::error('El asistente no respondió', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            $this->notice('El asistente no está disponible en este momento. Llámanos al '.config('clinic.phone').' y con gusto te atendemos.');

            return null;
        }
    }

    private function notice(string $text): void
    {
        $this->messages[] = ['role' => 'notice', 'content' => $text];
    }
}
