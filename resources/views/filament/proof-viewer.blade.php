@php $proof = $getRecord()->latestProof; @endphp
@if ($proof)
    <iframe src="{{ route('payment-proofs.show', $proof->id) }}" title="Comprobante de pago" style="width: 100%; height: 70vh; border: 0; border-radius: 12px;"></iframe>
    <a href="{{ route('payment-proofs.show', $proof->id) }}" target="_blank" rel="noopener" style="font-size: .875rem; text-decoration: underline;">Abrir en otra pestaña</a>
@else
    <p style="font-size: .875rem; opacity: .7;">Todavía no hay comprobante. Si el pago es en efectivo, se registra al marcar la llegada.</p>
@endif
