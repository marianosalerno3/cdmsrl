@component('mail::message')
# Carrello fermo: {{ $cliente->denominazione }}

Ciao {{ $agente?->nome }},

il carrello che hai caricato per **{{ $cliente->denominazione }}** è fermo da più di un giorno e non è ancora diventato un ordine. Il cliente non ha l'accesso al portale, quindi lo avvisiamo tramite te.

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
@endcomponent
