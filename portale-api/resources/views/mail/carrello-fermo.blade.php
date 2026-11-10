@component('mail::message')
# Il tuo carrello ti aspetta

Gentile {{ $cliente->denominazione }},

hai lasciato dei prodotti nel carrello del portale B2B. Li abbiamo tenuti da parte: puoi riprendere da dove avevi lasciato e completare l'ordine.

@component('mail::table')
| Prodotto | Qtà | Importo |
|:---|---:|---:|
@foreach ($carrello->righe as $riga)
| {{ $riga['nome'] ?? $riga['codice'] ?? 'Articolo' }}{{ ! empty($riga['taglia']) ? ' · '.$riga['taglia'] : '' }}{{ ! empty($riga['colore']) ? ' · '.$riga['colore'] : '' }} | {{ $riga['quantita'] }} | € {{ number_format($riga['prezzo'] * $riga['quantita'], 2, ',', '.') }} |
@endforeach
| **Totale merce (IVA esclusa)** | | **€ {{ number_format($carrello->totale, 2, ',', '.') }}** |
@endcomponent

@component('mail::button', ['url' => $carrelloUrl])
Riprendi il carrello
@endcomponent

Disponibilità e prezzi possono cambiare: nel carrello trovi sempre i valori aggiornati.

Grazie,
{{ config('app.name') }}

<small>Ricevi questa email perché hai acconsentito alle comunicazioni commerciali. <a href="{{ $annullaUrl }}">Non voglio più riceverle</a>.</small>
@endcomponent
