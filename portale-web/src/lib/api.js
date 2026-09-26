import axios from 'axios'

const TOKEN_KEY = 'token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

// --- request: Bearer token da localStorage ---
api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// --- response: 401 -> logout + redirect al login ---
let onUnauthorized = () => {}
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem('user')
      onUnauthorized()
    }
    return Promise.reject(error)
  },
)

/** Estrae il messaggio d'errore leggibile da una risposta axios (formato Laravel). */
export function errorMessage(error, fallback = 'Si è verificato un errore. Riprova.') {
  const data = error?.response?.data
  if (!data) return error?.message || fallback
  if (typeof data === 'string') return data
  if (data.message && !data.errors) return data.message
  if (data.errors) {
    const first = Object.values(data.errors)[0]
    return Array.isArray(first) ? first[0] : String(first)
  }
  return data.message || fallback
}

export { TOKEN_KEY }
