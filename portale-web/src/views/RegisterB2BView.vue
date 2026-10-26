<script setup>
import { ref } from 'vue'
import { api, errorMessage } from '@/lib/api'

const form = ref({
  ragione_sociale: '',
  partita_iva: '',
  codice_fiscale: '',
  codice_sdi: '',
  pec: '',
  email: '',
  password: '',
  password_confirmation: '',
  telefono: '',
  indirizzo: '',
  cap: '',
  citta: '',
  provincia: '',
  agente_codice: '',
})
const accettoPrivacy = ref(false)
const accettoComunicazioni = ref(false)
const documenti = ref([])
const loading = ref(false)
const error = ref('')
const done = ref(false)

function onFiles(e) {
  documenti.value = Array.from(e.target.files || [])
}

async function submit() {
  error.value = ''
  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Le due password non coincidono.'
    return
  }
  if (!accettoPrivacy.value) {
    error.value = 'Per registrarti devi accettare l’informativa privacy e le condizioni d’uso.'
    return
  }
  if (!accettoComunicazioni.value) {
    error.value = 'Per registrarti devi acconsentire alle comunicazioni commerciali (puoi annullare l’iscrizione in qualsiasi momento).'
    return
  }
  loading.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => v && fd.append(k, v))
    fd.append('accetto_privacy', '1')
    fd.append('accetto_comunicazioni', '1')
    documenti.value.forEach((f) => fd.append('documenti[]', f))
    await api.post('/register-b2b', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    done.value = true
  } catch (e) {
    error.value = errorMessage(e, 'Registrazione non riuscita.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl p-4 py-8">
    <div v-if="done" class="card p-8 text-center">
      <h1 class="text-xl font-bold">Registrazione inviata</h1>
      <p class="mt-2 text-sm text-zinc-500">
        Verificheremo i tuoi dati e i documenti. Quando la registrazione sarà approvata potrai accedere con
        l'email e la password che hai scelto: ti avviseremo via email.
      </p>
      <RouterLink to="/login" class="btn-primary mt-6">Torna al login</RouterLink>
    </div>

    <div v-else class="card p-6 sm:p-8">
      <h1 class="text-xl font-bold tracking-tight">Registrazione Cliente B2B</h1>
      <p class="mt-1 text-sm text-zinc-500">I campi con * sono obbligatori.</p>

      <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <div class="sm:col-span-2">
          <label class="label">Ragione sociale *</label>
          <input v-model="form.ragione_sociale" required class="field" />
        </div>
        <div>
          <label class="label">Partita IVA *</label>
          <input v-model="form.partita_iva" required class="field" />
        </div>
        <div>
          <label class="label">Codice fiscale</label>
          <input v-model="form.codice_fiscale" class="field" />
        </div>
        <div>
          <label class="label">Email aziendale *</label>
          <input v-model="form.email" type="email" required class="field" />
        </div>
        <div>
          <label class="label">Telefono</label>
          <input v-model="form.telefono" class="field" />
        </div>
        <div>
          <label class="label">Password * (min. 8 caratteri)</label>
          <input
            v-model="form.password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="field"
          />
        </div>
        <div>
          <label class="label">Conferma password *</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="field"
          />
        </div>
        <div>
          <label class="label">PEC {{ form.codice_sdi ? '' : '*' }}</label>
          <input v-model="form.pec" type="email" :required="!form.codice_sdi" class="field" />
        </div>
        <div>
          <label class="label">Codice SDI {{ form.pec ? '' : '*' }}</label>
          <input
            v-model="form.codice_sdi"
            :required="!form.pec"
            maxlength="7"
            pattern="[A-Za-z0-9]{6,7}"
            title="6 o 7 caratteri (lettere e numeri)"
            class="field uppercase"
          />
        </div>
        <p class="-mt-2 text-xs text-zinc-500 sm:col-span-2">
          Per la fatturazione elettronica inserisci almeno uno tra PEC e Codice SDI.
        </p>
        <div class="sm:col-span-2">
          <label class="label">Indirizzo sede *</label>
          <input v-model="form.indirizzo" required class="field" />
        </div>
        <div>
          <label class="label">CAP *</label>
          <input v-model="form.cap" required class="field" />
        </div>
        <div>
          <label class="label">Città *</label>
          <input v-model="form.citta" required class="field" />
        </div>
        <div>
          <label class="label">Provincia *</label>
          <input v-model="form.provincia" required maxlength="4" class="field" />
        </div>
        <div>
          <label class="label">Codice agente (se noto)</label>
          <input v-model="form.agente_codice" class="field" />
        </div>
        <div class="sm:col-span-2">
          <label class="label">Documenti (visura, doc. identità…)</label>
          <input
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png"
            class="field"
            @change="onFiles"
          />
        </div>

        <label class="flex items-start gap-3 rounded-lg bg-zinc-50 px-3 py-3 text-sm sm:col-span-2">
          <input v-model="accettoPrivacy" type="checkbox" required class="mt-0.5 size-4 shrink-0 accent-black" />
          <span>
            Dichiaro di aver letto e accetto l’<RouterLink to="/privacy" target="_blank" class="font-semibold underline"
              >informativa sulla privacy</RouterLink
            >
            e le <RouterLink to="/condizioni" target="_blank" class="font-semibold underline">condizioni d’uso del
            portale</RouterLink>. <span class="text-zinc-500">*</span>
          </span>
        </label>

        <label class="flex items-start gap-3 rounded-lg bg-zinc-50 px-3 py-3 text-sm sm:col-span-2">
          <input v-model="accettoComunicazioni" type="checkbox" required class="mt-0.5 size-4 shrink-0 accent-black" />
          <span>
            Acconsento a ricevere comunicazioni commerciali e promemoria (per esempio sui carrelli lasciati in sospeso) via email.
            <span class="text-zinc-500">Posso annullare l’iscrizione in qualsiasi momento dal link in ogni email. *</span>
          </span>
        </label>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 sm:col-span-2">
          {{ error }}
        </p>

        <div class="flex items-center gap-3 sm:col-span-2">
          <button class="btn-primary" :disabled="loading">
            {{ loading ? 'Invio…' : 'Invia registrazione' }}
          </button>
          <RouterLink to="/login" class="btn-link">Sei già registrato? Accedi</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>
