import { reactive, computed } from 'vue'
import { api, TOKEN_KEY } from '@/lib/api'
import { resetCustomers } from '@/stores/customer'
import { useCart } from '@/stores/cart'

const state = reactive({
  token: localStorage.getItem(TOKEN_KEY) || null,
  user: safeParse(localStorage.getItem('user')),
})

function safeParse(v) {
  try {
    return v ? JSON.parse(v) : null
  } catch {
    return null
  }
}

function persist() {
  if (state.token) localStorage.setItem(TOKEN_KEY, state.token)
  else localStorage.removeItem(TOKEN_KEY)
  if (state.user) localStorage.setItem('user', JSON.stringify(state.user))
  else localStorage.removeItem('user')
}

export function useAuth() {
  const isAuthenticated = computed(() => !!state.token)
  // il portale è usato da agenti e da clienti B2B approvati: il ruolo arriva dal login (/login, /me)
  const isCliente = computed(() => state.user?.ruolo === 'cliente')

  async function login(email, password) {
    const { data } = await api.post('/login', { email, password })
    state.token = data.token
    state.user = data.user
    persist()
  }

  async function refreshMe() {
    const { data } = await api.get('/me')
    state.user = data.user
    persist()
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch {
      /* token già invalido */
    }
    clear()
  }

  function clear() {
    state.token = null
    state.user = null
    persist()
    // pulizia dati di sessione legati all'agente
    ;['selectedCustomerId', 'cart_items', 'cart_draft_id', 'dash_tab', 'dash_season'].forEach((k) =>
      localStorage.removeItem(k),
    )
    resetCustomers()
    useCart().clear()
  }

  return { state, isAuthenticated, isCliente, login, logout, refreshMe, clear }
}
