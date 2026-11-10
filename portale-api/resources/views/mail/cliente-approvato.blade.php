@component('mail::message')
# Benvenuto, {{ $cliente->denominazione }}

La tua registrazione è stata approvata. Ora puoi accedere al portale B2B con l'email e la password scelte in fase di registrazione, consultare il catalogo con il tuo listino e inviare i tuoi ordini.

@component('mail::button', ['url' => $portaleUrl])
Accedi al portale
@endcomponent

Per qualsiasi dubbio rispondi a questa email.
@endcomponent
