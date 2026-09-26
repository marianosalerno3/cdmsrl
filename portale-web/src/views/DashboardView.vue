<script setup>
import { ref, onMounted, computed } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useAuth } from '@/stores/auth'
import { useConfig } from '@/stores/config'
import { useCustomer } from '@/stores/customer'
import { money, date } from '@/lib/format'
import Spinner from '@/components/Spinner.vue'
import EmptyState from '@/components/EmptyState.vue'

const { state: auth } = useAuth()
const { config } = useConfig()
const { state: customerState, load: loadCustomers } = useCustomer()

const loading = ref(true)
const error = ref('')
const orders = ref([])
const seasons = ref([])
const season = ref(localStorage.getItem('dash_season') || '')

// asset del brand serviti da public/ — path stringa per non farli risolvere da Vite
const bgVideo = '/videos/dashboard-desktop.mp4'

const monthlyOrders = computed(() => {
  const now = new Date()
  return orders.value.filter((o) => {
    const d = new Date(o.data_ordine)
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
  }).length
})

async function loadOrders() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/agent/orders', { params: { season: season.value || undefined } })
    orders.value = data.data ?? data ?? []
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function onSeason() {
  localStorage.setItem('dash_season', season.value)
  loadOrders()
}

onMounted(async () => {
  loadCustomers()
  try {
    const { data } = await api.get('/agent/seasons')
    seasons.value = data.data ?? data ?? []
  } catch {
    /* opzionale */
  }
  await loadOrders()
})
</script>

<template>
  <div class="space-y-6">
    <div class="relative overflow-hidden rounded-2xl bg-dark text-white">
      <video
        class="absolute inset-0 size-full object-cover opacity-30"
        autoplay
        muted
        loop
        playsinline
        poster=""
      >
        <source :src="bgVideo" type="video/mp4" />
      </video>
      <div class="relative p-6 sm:p-8">
        <p class="text-sm text-white/70">Benvenuto</p>
        <h1 class="text-2xl font-bold tracking-tight">{{ auth.user?.nome || 'Agente' }}</h1>
        <p class="mt-1 text-sm text-white/70">Codice agente {{ auth.user?.codice_agente }}</p>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card p-5">
        <p class="text-sm text-zinc-500">Ordini mensili</p>
        <p class="mt-1 text-3xl font-bold">{{ monthlyOrders }}</p>
        <p class="text-xs text-zinc-400">mese corrente</p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-zinc-500">Clienti portafoglio</p>
        <p class="mt-1 text-3xl font-bold">{{ customerState.list.length }}</p>
        <RouterLink to="/agent/new-client" class="btn-link">+ Nuovo cliente</RouterLink>
      </div>
      <div class="card p-5">
        <p class="text-xs uppercase tracking-wide text-zinc-400">Importo minimo nuovo ordine</p>
        <p class="mt-1 text-3xl font-bold">{{ money(config.importo_minimo_ordine) }}</p>
      </div>
    </div>

    <div class="card">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 p-4">
        <h2 class="text-lg font-bold">I tuoi ordini</h2>
        <select v-model="season" class="field !w-auto" @change="onSeason">
          <option value="">Tutte le stagioni</option>
          <option v-for="s in seasons" :key="s.codice" :value="s.codice">
            {{ s.codice }}<template v-if="s.nome"> — {{ s.nome }}</template>
          </option>
        </select>
      </div>

      <Spinner v-if="loading" label="Carico gli ordini…" />
      <p v-else-if="error" class="p-6 text-sm text-red-600">{{ error }}</p>
      <EmptyState
        v-else-if="!orders.length"
        title="Nessun ordine"
        subtitle="Gli ordini che invii dal catalogo compaiono qui."
        class="m-4"
      />
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-zinc-400">
              <th class="p-3">N. Ordine</th>
              <th class="p-3">Cliente</th>
              <th class="p-3">Stato</th>
              <th class="p-3 text-right">Totale</th>
              <th class="p-3">Data</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in orders" :key="o.id" class="border-t border-zinc-50">
              <td class="p-3 font-medium">{{ o.numero }}</td>
              <td class="p-3">{{ o.cliente_nome || o.cliente?.ragione_sociale || '—' }}</td>
              <td class="p-3">
                <span class="rounded-full bg-dark-glass px-2 py-0.5 text-xs font-medium capitalize">
                  {{ (o.stato || '').replaceAll('_', ' ') }}
                </span>
              </td>
              <td class="p-3 text-right font-semibold">{{ money(o.totale) }}</td>
              <td class="p-3 text-zinc-500">{{ date(o.data_ordine) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
