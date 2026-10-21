<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useCustomer } from '@/stores/customer'

const router = useRouter()
const { load: reloadCustomers } = useCustomer()

const form = ref({
  ragione_sociale: '',
  partita_iva: '',
  codice_fiscale: '',
  email: '',
  telefono: '',
  indirizzo: '',
  cap: '',
  citta: '',
  provincia: '',
})
const loading = ref(false)
const error = ref('')
const done = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/clients', form.value)
    await reloadCustomers(true)
    done.value = true
  } catch (e) {
    error.value = errorMessage(e, 'Invio richiesta non riuscito.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <div v-if="done" class="card p-8 text-center">
      <h1 class="text-xl font-bold">Richiesta cliente inviata</h1>
      <p class="mt-2 text-sm text-zinc-500">
        Il cliente sarà attivo dopo l'approvazione dell'amministratore.
      </p>
      <button class="btn-primary mt-6" @click="router.push({ name: 'dashboard' })">
        Torna alla dashboard
      </button>
    </div>

    <div v-else class="card p-6 sm:p-8">
      <h1 class="text-xl font-bold tracking-tight">Nuovo cliente</h1>
      <p class="mt-1 text-sm text-zinc-500">
        Proponi un nuovo cliente per il tuo portafoglio. Sarà sottoposto ad approvazione.
      </p>

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
          <label class="label">Email *</label>
          <input v-model="form.email" type="email" required class="field" />
        </div>
        <div>
          <label class="label">Telefono</label>
          <input v-model="form.telefono" class="field" />
        </div>
        <div class="sm:col-span-2">
          <label class="label">Indirizzo</label>
          <input v-model="form.indirizzo" class="field" />
        </div>
        <div>
          <label class="label">CAP</label>
          <input v-model="form.cap" class="field" />
        </div>
        <div>
          <label class="label">Città</label>
          <input v-model="form.citta" class="field" />
        </div>
        <div>
          <label class="label">Provincia</label>
          <input v-model="form.provincia" maxlength="4" class="field" />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 sm:col-span-2">
          {{ error }}
        </p>

        <div class="sm:col-span-2">
          <button class="btn-primary" :disabled="loading">
            {{ loading ? 'Invio…' : 'Invia richiesta' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
