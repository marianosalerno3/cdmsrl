@component('mail::message')
# Nuovo ordine dal portale — {{ $ordine->numero }}

@if ($ordine->da_cliente)
Il cliente ha inserito da solo un nuovo ordine dal portale. Prendilo in carico e caricalo su WinMino.
@else
Un agente ha inviato un nuovo ordine. Prendilo in carico e caricalo su WinMino.
@endif

@component('mail::table')
| | |
|---|---|
| Inserito da | {{ $ordine->da_cliente ? 'Cliente (accesso diretto al portale)' : 'Agente' }} |
| Cliente | {{ $ordine->cliente_nome }} |
| Agente | {{ $ordine->agente?->nome ?? '—' }} |
| Data | {{ $ordine->data_ordine?->format('d/m/Y') }} |
| Metodo di pagamento | {{ $ordine->metodo_pagamento?->getLabel() ?? '—' }} |
| Listino | {{ $ordine->listino_applicato?->value ?? '—' }} |
| Pezzi | {{ $ordine->totale_pezzi }} |
| **Totale (IVA incl.)** | **€ {{ number_format($ordine->totale, 2, ',', '.') }}** |
@endcomponent

@component('mail::table')
| Codice | Articolo | Taglia | Colore | Q.tà | Prezzo |
|---|---|---|---|--:|--:|
@foreach ($ordine->righe as $r)
| {{ $r->prodotto_codice }} | {{ $r->prodotto_nome }} | {{ $r->taglia }} | {{ $r->colore }} | {{ $r->quantita }} | € {{ number_format($r->prezzo_unitario, 2, ',', '.') }} |
@endforeach
@endcomponent

Indirizzo di spedizione: {{ $ordine->indirizzo_spedizione }}

@if ($ordine->note_agente)
**Note agente:** {{ $ordine->note_agente }}
@endif

@component('mail::button', ['url' => $panelUrl])
Apri nel pannello
@endcomponent

Dettaglio completo nel PDF allegato.
@endcomponent
