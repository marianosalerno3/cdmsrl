<script setup>
import { ref, onMounted, watch } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useConfig } from '@/stores/config'
import { useCustomer } from '@/stores/customer'
import ProductCard from '@/components/ProductCard.vue'
import CustomerSelect from '@/components/CustomerSelect.vue'
import Spinner from '@/components/Spinner.vue'
import EmptyState from '@/components/EmptyState.vue'

const { filters, load: loadConfig, state: cfgState } = useConfig()
const { selected } = useCustomer()

const search = ref(localStorage.getItem('catalog_search') || '')
const category = ref(localStorage.getItem('catalog_category') || '')
const season = ref(localStorage.getItem('catalog_season') || '')
const page = ref(Number(localStorage.getItem('catalog_page')) || 1)

const products = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref('')

let debounce
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    fetch()
  }, 350)
})
watch([category, season], () => {
  page.value = 1
  fetch()
})
watch(() => selected.value?.id, () => fetch())

function persist() {
  localStorage.setItem('catalog_search', search.value)
  localStorage.setItem('catalog_category', category.value)
  localStorage.setItem('catalog_season', season.value)
  localStorage.setItem('catalog_page', String(page.value))
}

async function fetch() {
  if (!selected.value) {
    products.value = []
    return
  }
  loading.value = true
  error.value = ''
  persist()
  try {
    const { data } = await api.get('/products', {
      params: {
        search: search.value || undefined,
        category: category.value || undefined,
        season: season.value || undefined,
        page: page.value,
        customer_id: selected.value.id,
      },
    })
    products.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function goPage(p) {
  page.value = p
  fetch()
}

onMounted(async () => {
  if (!cfgState.loaded) {
    try {
      await loadConfig()
    } catch {
      /* ignore */
    }
  }
  fetch()
})
</script>

<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold tracking-tight">Catalogo</h1>

    <div class="card space-y-4 p-4">
      <CustomerSelect />

      <div class="grid gap-3 sm:grid-cols-3">
        <input v-model="search" class="field" placeholder="Cerca per nome o codice…" />
        <select v-model="category" class="field">
          <option value="">Tutte le categorie</option>
          <option v-for="c in filters.categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="season" class="field">
          <option value="">Tutte le stagioni</option>
          <option v-for="s in filters.seasons" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </div>

    <EmptyState
      v-if="!selected"
      title="Seleziona un cliente"
      subtitle="Scegli il cliente per vedere il catalogo con i prezzi del suo listino."
    />

    <template v-else>
      <Spinner v-if="loading" label="Carico i prodotti…" />
      <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
      <EmptyState v-else-if="!products.length" title="Nessun prodotto trovato" />

      <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <ProductCard v-for="p in products" :key="p.id" :product="p" />
      </div>

      <div v-if="meta.last_page > 1" class="flex items-center justify-center gap-2 pt-2">
        <button class="btn-ghost" :disabled="page <= 1" @click="goPage(page - 1)">Precedente</button>
        <span class="text-sm text-zinc-500">{{ page }} / {{ meta.last_page }}</span>
        <button class="btn-ghost" :disabled="page >= meta.last_page" @click="goPage(page + 1)">
          Successivo
        </button>
      </div>
    </template>
  </div>
</template>
