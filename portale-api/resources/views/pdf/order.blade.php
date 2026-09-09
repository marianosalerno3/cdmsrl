@php /** @var \App\Models\OrdineB2B $ordine */ @endphp
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .muted { color: #666; }
    .row { width: 100%; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; }
    th { background: #f4f4f5; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; }
    td.num, th.num { text-align: right; }
    .totali { margin-top: 12px; float: right; width: 45%; }
    .totali td { border: 0; padding: 3px 8px; }
    .totali .grand td { border-top: 2px solid #111; font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
    <table class="row"><tr>
        <td style="border:0;">
            <h1>{{ config('app.name') }}</h1>
            <div class="muted">Conferma d'ordine</div>
        </td>
        <td style="border:0; text-align:right;">
            <strong>{{ $ordine->numero }}</strong><br>
            <span class="muted">{{ $ordine->data_ordine?->format('d/m/Y') }}</span>
        </td>
    </tr></table>

    <table class="row" style="margin-top:10px;"><tr>
        <td style="border:0; width:50%; vertical-align:top;">
            <strong>Cliente</strong><br>
            {{ $ordine->cliente_nome }}<br>
            {{ $ordine->cliente_email }}<br>
            {{ $ordine->cliente_telefono }}
        </td>
        <td style="border:0; width:50%; vertical-align:top;">
            <strong>Spedizione</strong><br>
            {{ $ordine->indirizzo_spedizione }}<br>
            <span class="muted">Agente:</span> {{ $ordine->agente?->nome }}<br>
            <span class="muted">Pagamento:</span> {{ $ordine->metodo_pagamento?->getLabel() }}
        </td>
    </tr></table>

    <table>
        <thead>
        <tr>
            <th>Codice</th><th>Prodotto</th><th>Taglia</th><th>Colore</th>
            <th class="num">Q.tà</th><th class="num">Prezzo</th><th class="num">Totale</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($ordine->righe as $r)
            <tr>
                <td>{{ $r->prodotto_codice }}</td>
                <td>{{ $r->prodotto_nome }}</td>
                <td>{{ $r->taglia }}</td>
                <td>{{ $r->colore }}</td>
                <td class="num">{{ $r->quantita }}</td>
                <td class="num">€ {{ number_format($r->prezzo_unitario, 2, ',', '.') }}</td>
                <td class="num">€ {{ number_format($r->totale_riga, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totali">
        <tr><td>Subtotale</td><td class="num">€ {{ number_format($ordine->subtotale, 2, ',', '.') }}</td></tr>
        <tr><td>Spese spedizione</td><td class="num">€ {{ number_format($ordine->spese_spedizione, 2, ',', '.') }}</td></tr>
        <tr><td>IVA ({{ number_format($ordine->iva_perc, 0) }}%)</td><td class="num">€ {{ number_format($ordine->iva_importo, 2, ',', '.') }}</td></tr>
        <tr class="grand"><td>Totale ordine</td><td class="num">€ {{ number_format($ordine->totale, 2, ',', '.') }}</td></tr>
    </table>

    <div style="clear:both;"></div>
    @if ($ordine->note_agente)
        <p style="margin-top:24px;"><strong>Note:</strong> {{ $ordine->note_agente }}</p>
    @endif
</body>
</html>
