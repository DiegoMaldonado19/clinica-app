<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ADR-041: la foto sale de la app, como el comprobante. La ve su dueño o el
 * personal; los demas reciben 404. Nunca se sirve un archivo fuera de
 * `avatars/`, aunque alguien altere la ruta guardada.
 */
class AvatarController extends Controller
{
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();
        $path = (string) $user->avatar_path;

        abort_unless(str_starts_with($path, 'avatars/') && ($viewer->is($user) || $viewer->isStaff()), 404);

        return Storage::disk('s3')->response($path, headers: [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
