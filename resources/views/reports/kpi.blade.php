{{-- dompdf no lee las woff2 del build: DejaVu viene con dompdf y cubre ≤, ≥ y las tildes. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #2C2C28; }
        .brand { font-family: 'DejaVu Serif', serif; font-size: 13px; color: #4A5D4E; margin: 0; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 17px; margin: 6px 0 2px; }
        h2 { font-size: 12px; color: #4A5D4E; margin: 22px 0 6px; }
        .muted { color: #6B6B63; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #E7ECE8; padding: 5px 7px; }
        td { padding: 5px 7px; border-bottom: 1px solid #DDD8CC; vertical-align: top; }
        .number { text-align: right; white-space: nowrap; }
        .value { text-align: right; white-space: nowrap; font-weight: bold; }
    </style>
</head>
<body>
    <p class="brand">{{ config('clinic.name') }}</p>
    <h1>{{ $title }}</h1>
    <p class="muted">Generado el {{ now(config('clinic.timezone'))->locale('es')->translatedFormat('j \d\e F \d\e Y, H:i') }}</p>

    <h2>Indicadores</h2>
    <table>
        <tr><th>Indicador</th><th class="number">Valor</th><th>Meta</th></tr>
        @foreach ($report->indicators as $kpi)
            <tr>
                <td>
                    {{ trim($kpi->code.' '.$kpi->label) }}
                    @if ($kpi->note !== '')<br><span class="muted">{{ $kpi->note }}</span>@endif
                </td>
                <td class="value">{{ $kpi->display() }}</td>
                <td class="muted">{{ $kpi->goal }}</td>
            </tr>
        @endforeach
    </table>

    @if ($report->revenueByService !== [])
        <h2>Ingreso por servicio</h2>
        <table>
            <tr><th>Servicio</th><th class="number">Ingreso cobrado</th></tr>
            @foreach ($report->revenueByService as $service => $cents)
                <tr><td>{{ $service }}</td><td class="number">{{ \App\Shared\Domain\ValueObject\Money::gtq($cents)->format() }}</td></tr>
            @endforeach
            <tr><td><strong>Total</strong></td><td class="value">{{ \App\Shared\Domain\ValueObject\Money::gtq(array_sum($report->revenueByService))->format() }}</td></tr>
        </table>
    @endif

    <h2>Ocupación por día</h2>
    <p class="muted">Sobre las horas publicadas, sin los bloqueos de agenda.</p>
    <table>
        <tr><th>Día</th><th class="number">Horas publicadas</th><th class="number">Horas ocupadas</th><th class="number">Ocupación</th></tr>
        @foreach ($report->occupancyByWeekday as $weekday => $day)
            <tr>
                <td>{{ \App\Analytics\Domain\Report::WEEKDAYS[$weekday] }}</td>
                <td class="number">{{ $day['published'] }}</td>
                <td class="number">{{ $day['occupied'] }}</td>
                <td class="value">{{ number_format($day['rate'], 1) }} %</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
