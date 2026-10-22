<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Shared\Infrastructure\Persistence\Catalog;
use Illuminate\Support\Facades\DB;

/** Los canales por los que cada persona quiere sus avisos. Sin elección, el correo. */
final readonly class NotificationPreferences
{
    public function __construct(private Catalog $catalog) {}

    /** @return list<string> códigos de los canales elegidos; vacío si nunca eligió */
    public function of(string $userId): array
    {
        return DB::table('notification_preferences')
            ->join('notification_channels', 'notification_channels.id', '=', 'notification_preferences.channel_id')
            ->where('notification_preferences.user_id', $userId)
            ->pluck('notification_channels.code')
            ->all();
    }

    /** @param list<string> $codes */
    public function choose(string $userId, array $codes): void
    {
        DB::table('notification_preferences')->where('user_id', $userId)->delete();
        DB::table('notification_preferences')->insert(array_map(fn (string $code): array => [
            'user_id' => $userId,
            'channel_id' => $this->catalog->id('notification_channels', $code),
            'created_at' => now(),
            'updated_at' => now(),
        ], $codes));
    }
}
