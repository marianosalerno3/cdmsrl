<script setup>
import { COMPANY_NAME } from '@/lib/brand'
</script>

<template>
  <div class="mx-auto max-w-2xl p-4 py-8">
    <div class="card space-y-4 p-6 text-sm leading-relaxed text-zinc-700 sm:p-8">
      <RouterLink to="/register-b2b" class="btn-link">← Torna alla registrazione</RouterLink>
      <h1 class="text-xl font-bold tracking-tight text-ink">Condizioni d'uso del portale B2B</h1>
      <p class="text-xs text-zinc-500">Portale B2B {{ COMPANY_NAME }} — versione 2026-10</p>

      <h2 class="pt-2 text-base font-bold text-ink">1. A chi è rivolto</h2>
      <p>
        Il portale è riservato a operatori professionali (aziende, ditte individuali, rivenditori) e ai loro agenti di
        vendita. Non è destinato ai consumatori: per gli acquisti dei privati esiste il negozio online dedicato.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">2. Registrazione e approvazione</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li>I dati forniti in registrazione devono essere veritieri e aggiornati.</li>
        <li>
          L'accesso viene attivato solo dopo la verifica dei dati e dei documenti da parte nostra, che può accettare o
          rifiutare la richiesta. Finché non è approvata non è possibile accedere.
        </li>
        <li>
          L'account è personale e riservato all'azienda registrata. Le credenziali vanno custodite con cura e non
          comunicate a terzi: sei responsabile di ciò che avviene con il tuo accesso. In caso di smarrimento o uso non
          autorizzato avvisaci subito.
        </li>
      </ul>

      <h2 class="pt-2 text-base font-bold text-ink">3. Catalogo, prezzi e ordini</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li>
          I prezzi mostrati sono quelli del listino a te riservato, al netto di IVA; l'IVA e le spese di spedizione
          sono indicate nel carrello prima della conferma.
        </li>
        <li>
          Disponibilità, prezzi e informazioni possono cambiare: l'ordine inviato dal portale è una richiesta che viene
          presa in carico e confermata dal nostro ufficio commerciale. <strong>[DA CONFERMARE: modalità e tempi di
          conferma dell'ordine]</strong>
        </li>
        <li>Pagamento, consegna e resi seguono le condizioni commerciali concordate con la nostra azienda.</li>
      </ul>

      <h2 class="pt-2 text-base font-bold text-ink">4. Uso corretto</h2>
      <p>
        È vietato usare il portale per scopi diversi da quelli commerciali previsti, tentare di accedere ad aree o dati
        non propri, copiare in modo sistematico il catalogo o diffondere a terzi i listini e le informazioni riservate.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">5. Sospensione</h2>
      <p>
        Possiamo sospendere o chiudere l'accesso in caso di violazione di queste condizioni, di dati non veritieri o di
        mancati pagamenti.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">6. Privacy</h2>
      <p>
        Il trattamento dei dati personali è descritto nell'<RouterLink to="/privacy" class="underline"
          >informativa sulla privacy</RouterLink
        >.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">7. Legge applicabile</h2>
      <p>
        Le presenti condizioni sono regolate dalla legge italiana. Per ogni controversia è competente il foro di
        <strong>[DA COMPLETARE: es. Nola/Napoli]</strong>.
      </p>

      <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
        Testo di lavoro: i punti tra parentesi quadre vanno completati e il testo va fatto verificare dal consulente
        legale prima della pubblicazione.
      </p>
    </div>
  </div>
</template>
