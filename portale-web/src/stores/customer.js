import { reactive, computed, watch } from 'vue'
import { api } from '@/lib/api'
import { useCart } from '@/stores/cart'

const state = reactive({
  list: [],
  loading: false,
  selectedId: localStorage.getItem('selectedCustomerId') || null,
})

let loadedOnce = false

export function useCustomer() {
  const selected = computed(() => state.list.find((c) => String(c.id) === String(state.selectedId)) || null)

  async function load(force = false) {
    if (loadedOnce && !force) return
    state.loading = true
    try {
      const { data } = await api.get('/agent/customers')
      state.list = data.data ?? data ?? []
      loadedOnce = true
    } finally {
      state.loading = false
    }
  }

  function select(id) {
    if (String(id) === String(state.selectedId)) return
    state.selectedId = id ? String(id) : null
    if (state.selectedId) localStorage.setItem('selectedCustomerId', state.selectedId)
    else localStorage.removeItem('selectedCustomerId')
    // il carrello è vincolato al cliente: cambiando cliente si svuota
    useCart().clear()
  }

  return { state, selected, load, select }
}

// mantiene coerente lo storage se qualcuno modifica selectedId altrove
watch(
  () => state.selectedId,
  (v) => {
    if (v) localStorage.setItem('selectedCustomerId', v)
    else localStorage.removeItem('selectedCustomerId')
  },
)
