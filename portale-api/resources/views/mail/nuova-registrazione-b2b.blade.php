@component('mail::message')
# Nuova registrazione B2B da approvare

Un nuovo cliente si è registrato dal portale e non può accedere finché non viene approvato.

@component('mail::table')
| | |
|---|---|
| Ragione sociale | {{ $cliente->ragione_sociale }} |
| Partita IVA | {{ $cliente->partita_iva }} |
| Email | {{ $cliente->email }} |
| Telefono | {{ $cliente->telefono ?? '—' }} |
| Sede | {{ $cliente->indirizzo }}, {{ $cliente->cap }} {{ $cliente->citta }} ({{ $cliente->provincia }}) |
| Agente indicato | {{ $cliente->agente?->nome ?? '—' }} |
| Documenti allegati | {{ count($cliente->documenti ?? []) }} |
@endcomponent

@component('mail::button', ['url' => $panelUrl])
Apri l'elenco clienti
@endcomponent

Dopo le verifiche, usa l'azione **Approva** sulla riga del cliente: riceverà un'email e potrà accedere.
@endcomponent
