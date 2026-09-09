@component('mail::message')
# Conferma ordine {{ $ordine->numero }}

Gentile {{ $ordine->cliente_nome }},

abbiamo ricevuto il vostro ordine del {{ $ordine->data_ordine?->format('d/m/Y') }}.

@component('mail::table')
| | |
|---|---:|
| Subtotale | € {{ number_format($ordine->subtotale, 2, ',', '.') }} |
| Spese spedizione | € {{ number_format($ordine->spese_spedizione, 2, ',', '.') }} |
| IVA ({{ number_format($ordine->iva_perc, 0) }}%) | € {{ number_format($ordine->iva_importo, 2, ',', '.') }} |
| **Totale** | **€ {{ number_format($ordine->totale, 2, ',', '.') }}** |
@endcomponent

Il dettaglio completo è nel PDF allegato.

Grazie,
{{ config('app.name') }}
@endcomponent
