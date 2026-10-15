<script setup>
import { COMPANY_NAME } from '@/lib/brand'
</script>

<template>
  <div class="mx-auto max-w-2xl p-4 py-8">
    <div class="card space-y-4 p-6 text-sm leading-relaxed text-zinc-700 sm:p-8">
      <RouterLink to="/register-b2b" class="btn-link">← Torna alla registrazione</RouterLink>
      <h1 class="text-xl font-bold tracking-tight text-ink">Informativa sulla privacy</h1>
      <p class="text-xs text-zinc-500">
        Informativa ai sensi dell'art. 13 del Regolamento UE 2016/679 (GDPR) — Portale B2B {{ COMPANY_NAME }} —
        versione 2026-10
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">1. Titolare del trattamento</h2>
      <p>
        Il titolare del trattamento è <strong>CDM SRL</strong>, con sede in Via Boscofangone, Nola (NA), P.IVA
        <strong>05325151214</strong>. Per qualsiasi richiesta sui tuoi dati:
        <a href="mailto:info@oltretempo.it" class="underline">info@oltretempo.it</a>.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">2. Quali dati trattiamo</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li>
          <strong>Dati dell'azienda e del referente:</strong> ragione sociale, partita IVA, codice fiscale, PEC o codice
          SDI, indirizzo della sede, telefono, email del referente.
        </li>
        <li><strong>Documenti</strong> che carichi in registrazione (es. visura camerale, documento di identità).</li>
        <li><strong>Credenziali di accesso:</strong> email e password (conservata in forma cifrata).</li>
        <li><strong>Dati degli ordini:</strong> prodotti, importi, indirizzi di consegna, metodo di pagamento.</li>
        <li><strong>Dati tecnici di utilizzo</strong> del portale, necessari al funzionamento e alla sicurezza.</li>
      </ul>

      <h2 class="pt-2 text-base font-bold text-ink">3. Perché li trattiamo e su quale base</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li>
          <strong>Valutare la tua richiesta di registrazione, creare e gestire il tuo accesso</strong> e gestire gli
          ordini, le consegne e la fatturazione (esecuzione di misure precontrattuali e di un contratto, art. 6.1.b).
        </li>
        <li>
          <strong>Adempiere a obblighi di legge</strong> fiscali e contabili (art. 6.1.c).
        </li>
        <li>
          <strong>Sicurezza del portale</strong> e prevenzione di accessi non autorizzati o abusi (legittimo interesse,
          art. 6.1.f).
        </li>
      </ul>
      <p>Non utilizziamo i tuoi dati per finalità di marketing senza un consenso separato.</p>

      <h2 class="pt-2 text-base font-bold text-ink">4. A chi comunichiamo i dati</h2>
      <p>
        I dati sono trattati dal personale autorizzato e, per le sole finalità indicate, da fornitori che agiscono come
        responsabili del trattamento: hosting del portale (Hetzner Online GmbH, server nell'Unione Europea), invio delle
        email (Brevo), pagamenti con carta (Stripe), gestionale aziendale e consulenti informatici e fiscali. Agli agenti
        di vendita a te assegnati sono visibili i dati necessari a seguire i tuoi ordini. I dati non sono diffusi.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">5. Per quanto tempo li conserviamo</h2>
      <p>
        Per tutta la durata del rapporto commerciale e, dopo, per il tempo previsto dalla legge (ad esempio 10 anni per
        i documenti contabili). Se la registrazione non viene approvata, i dati e i documenti inviati vengono conservati
        solo per il tempo necessario a gestire la richiesta e poi cancellati.
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">6. I tuoi diritti</h2>
      <p>
        Puoi chiedere in qualsiasi momento l'accesso ai dati, la rettifica, la cancellazione, la limitazione, la
        portabilità e opporti al trattamento, scrivendo a
        <a href="mailto:info@oltretempo.it" class="underline">info@oltretempo.it</a>. Hai inoltre diritto di proporre
        reclamo al Garante per la protezione dei dati personali (garanteprivacy.it).
      </p>

      <h2 class="pt-2 text-base font-bold text-ink">7. Conferimento dei dati</h2>
      <p>
        I dati contrassegnati come obbligatori nel modulo sono necessari per valutare la richiesta e attivare
        l'accesso: senza di essi non è possibile completare la registrazione.
      </p>

    </div>
  </div>
</template>
