<script setup>
import { ref, onMounted } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useConfig } from '@/stores/config'
import { date } from '@/lib/format'
import Spinner from '@/components/Spinner.vue'
import EmptyState from '@/components/EmptyState.vue'

const { config } = useConfig()

const loading = ref(true)
const error = ref('')
const returns = ref([])
const orders = ref([])

const showForm = ref(false)
const form = ref({ ordine_originale_id: '', motivazione: '' })
const righe = ref([{ variante_resa_id: '', variante_richiesta_id: '', quantita: 1 }])
const submitting = ref(false)
const formError = ref('')

async function loadAll() {
  loading.value = true
  error.value = ''
  try {
    const [{ data: r }, { data: o }] = await Promise.all([
      api.get('/agent/returns'),
      api.get('/agent/orders'),
    ])
    returns.value = r.data ?? r ?? []
    orders.value = (o.data ?? o ?? []).slice(0, 50)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function addRiga() {
  righe.value.push({ variante_resa_id: '', variante_richiesta_id: '', quantita: 1 })
}

async function submit() {
  formError.value = ''
  submitting.value = true
  try {
    await api.post('/returns', {
      ordine_originale_id: form.value.ordine_originale_id,
      motivazione: form.value.motivazione,
      righe: righe.value.filter((r) => r.variante_resa_id && r.quantita > 0),
    })
    showForm.value = false
    form.value = { ordine_originale_id: '', motivazione: '' }
    righe.value = [{ variante_resa_id: '', variante_richiesta_id: '', quantita: 1 }]
    await loadAll()
  } catch (e) {
    formError.value = errorMessage(e, 'Invio richiesta non riuscito.')
  } finally {
    submitting.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold tracking-tight">Sostituzioni</h1>
      <button class="btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Chiudi' : 'Nuova richiesta' }}
      </button>
    </div>

    <div class="card p-4 text-sm text-zinc-600">
      <p class="font-semibold">Come funzionano le sostituzioni</p>
      <p class="mt-1">
        Gli ordini programmati evasi entro {{ config.giorni_evasione_programmati }} giorni possono
        essere oggetto di richiesta di cambio taglia/colore.
      </p>
    </div>

    <div v-if="showForm" class="card space-y-4 p-4">
      <div>
        <label class="label">Ordine originale</label>
        <select v-model="form.ordine_originale_id" class="field">
          <option value="">Seleziona un ordine…</option>
          <option v-for="o in orders" :key="o.id" :value="o.id">
            {{ o.numero }} — {{ o.cliente_nome || o.cliente?.ragione_sociale }}
          </option>
        </select>
      </div>

      <div class="space-y-2">
        <label class="label">Articoli</label>
        <div
          v-for="(r, idx) in righe"
          :key="idx"
          class="grid gap-2 sm:grid-cols-[1fr_1fr_5rem]"
        >
          <input v-model="r.variante_resa_id" class="field" placeholder="SKU / ID variante resa" />
          <input
            v-model="r.variante_richiesta_id"
            class="field"
            placeholder="SKU / ID variante richiesta"
          />
          <input v-model.number="r.quantita" type="number" min="1" class="field text-center" />
        </div>
        <button class="btn-link" @click="addRiga">+ Aggiungi articolo</button>
      </div>

      <div>
        <label class="label">Motivazione</label>
        <textarea v-model="form.motivazione" rows="2" class="field"></textarea>
      </div>

      <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>

      <button
        class="btn-primary"
        :disabled="!form.ordine_originale_id || submitting"
        @click="submit"
      >
        {{ submitting ? 'Invio…' : 'Invia richiesta sostituzione' }}
      </button>
    </div>

    <Spinner v-if="loading" />
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
    <EmptyState v-else-if="!returns.length" title="Nessuna sostituzione richiesta" />

    <div v-else class="card divide-y divide-zinc-100">
      <div v-for="s in returns" :key="s.id" class="flex items-center justify-between p-4 text-sm">
        <div>
          <p class="font-medium">{{ s.numero }}</p>
          <p class="text-xs text-zinc-500">
            Ordine {{ s.ordine_originale?.numero || s.ordine_originale_id }} · {{ date(s.created_at) }}
          </p>
        </div>
        <span class="rounded-full bg-dark-glass px-2 py-0.5 text-xs font-medium capitalize">
          {{ s.stato }}
        </span>
      </div>
    </div>
  </div>
</template>
