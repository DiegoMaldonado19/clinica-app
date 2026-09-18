<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Doc 05 §7: quien no puede verlo recibe 404, no 403. Un 403 confirmaria que
 * el comprobante existe.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Request $request, string $proof): StreamedResponse
    {
        $record = PaymentProof::with('payment.appointment')->findOrFail($proof);
        $user = $request->user();

        $allowed = $user->hasAbility('payment.view.any')
            || ($user->hasAbility('payment.view.own') && $record->payment->appointment->patient_id === $user->getKey());

        abort_unless($allowed, 404);

        return Storage::disk('s3')->response($record->file_path, 'comprobante.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprobante.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
