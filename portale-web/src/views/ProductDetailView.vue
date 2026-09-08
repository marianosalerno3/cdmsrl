<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useCustomer } from '@/stores/customer'
import { useCart } from '@/stores/cart'
import Spinner from '@/components/Spinner.vue'
import EmptyState from '@/components/EmptyState.vue'
import SizeColorMatrix from '@/components/SizeColorMatrix.vue'

const route = useRoute()
const router = useRouter()
const { selected } = useCustomer()
const cart = useCart()

const product = ref(null)
const loading = ref(true)
const error = ref('')
const activeImg = ref(0)

const images = computed(() => product.value?.immagini ?? [])

async function fetch() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/products/${route.params.id}`, {
      params: { customer_id: selected.value?.id },
    })
    product.value = data.data ?? data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function onAdd(lines) {
  cart.addMany(lines)
  router.push({ name: 'cart' })
}

onMounted(fetch)
</script>

<template>
  <div class="space-y-5">
    <RouterLink to="/catalog" class="btn-link">← Torna al catalogo</RouterLink>

    <Spinner v-if="loading" />
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>

    <template v-else-if="product">
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-3">
          <div class="card aspect-square overflow-hidden bg-zinc-100">
            <img
              v-if="images.length"
              :src="images[activeImg]"
              :alt="product.nome"
              class="size-full object-cover"
            />
            <div v-else class="grid size-full place-items-center text-sm text-zinc-400">
              nessuna immagine
            </div>
          </div>
          <div v-if="images.length > 1" class="flex gap-2">
            <button
              v-for="(img, i) in images"
              :key="i"
              class="size-16 overflow-hidden rounded-lg ring-2"
              :class="i === activeImg ? 'ring-dark' : 'ring-transparent'"
              @click="activeImg = i"
            >
              <img :src="img" class="size-full object-cover" />
            </button>
          </div>
        </div>

        <div class="space-y-3">
          <div class="text-xs uppercase tracking-wide text-zinc-400">
            {{ product.codice }} · {{ product.categoria }} · {{ product.stagione }}
          </div>
          <h1 class="text-2xl font-bold tracking-tight">{{ product.nome }}</h1>
          <p v-if="product.descrizione" class="text-sm text-zinc-600">{{ product.descrizione }}</p>
          <dl class="grid grid-cols-2 gap-2 text-sm">
            <div v-if="product.composizione">
              <dt class="text-zinc-400">Composizione</dt>
              <dd>{{ product.composizione }}</dd>
            </div>
            <div v-if="product.tessuto">
              <dt class="text-zinc-400">Tessuto</dt>
              <dd>{{ product.tessuto }}</dd>
            </div>
          </dl>

          <EmptyState
            v-if="!selected"
            title="Seleziona un cliente"
            subtitle="Serve un cliente per vedere prezzi e comporre l'ordine."
            class="mt-4"
          />
        </div>
      </div>

      <div v-if="selected">
        <h2 class="mb-2 text-lg font-bold">Dettagli taglie / colori</h2>
        <SizeColorMatrix :product="product" :variants="product.varianti" @add="onAdd" />
      </div>
    </template>
  </div>
</template>
