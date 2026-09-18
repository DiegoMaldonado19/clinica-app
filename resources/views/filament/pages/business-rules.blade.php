<x-filament-panels::page>
    <x-filament::section heading="Valores vigentes" description="Un valor programado a futuro no aparece aquí hasta que entra en vigor.">
        <table style="width: 100%; font-size: .875rem; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; opacity: .7;">
                    <th style="padding: .5rem;">Regla</th>
                    <th style="padding: .5rem;">Parámetro</th>
                    <th style="padding: .5rem;">Valor</th>
                    <th style="padding: .5rem;">Vigente desde</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->current() as $row)
                    <tr style="border-top: 1px solid rgba(0,0,0,.08);">
                        <td style="padding: .5rem; font-weight: 600;">{{ $row->rule_code }}</td>
                        <td style="padding: .5rem;">{{ $row->param_key }}</td>
                        <td style="padding: .5rem; font-family: monospace;">{{ $row->param_value }}</td>
                        <td style="padding: .5rem;">{{ \Illuminate\Support\Carbon::parse($row->effective_from, 'UTC')->tz(config('clinic.timezone'))->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
