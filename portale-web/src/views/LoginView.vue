<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/stores/auth'
import { useConfig } from '@/stores/config'
import { api, errorMessage } from '@/lib/api'

const route = useRoute()
const router = useRouter()
const { login } = useAuth()
const { load: loadConfig } = useConfig()

const mode = ref('login') // 'login' | 'forgot'
const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const notice = ref('')

async function submit() {
  error.value = ''
  notice.value = ''
  loading.value = true
  try {
    if (mode.value === 'login') {
      await login(form.value.email, form.value.password)
      try {
        await loadConfig()
      } catch {
        /* non bloccante */
      }
      router.push(route.query.redirect || { name: 'dashboard' })
    } else {
      await api.post('/forgot-password', { email: form.value.email })
      notice.value = 'Se l’indirizzo è registrato, riceverai un’email con le istruzioni.'
    }
  } catch (e) {
    error.value = errorMessage(e, 'Credenziali non valide.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen place-items-center p-4">
    <div class="card w-full max-w-md p-8">
      <h1 class="text-center text-2xl font-bold tracking-tight">Portale Agenti B2B</h1>
      <p class="mt-1 text-center text-sm text-zinc-500">
        {{ mode === 'login' ? 'Accedi al tuo account' : 'Recupero password' }}
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="label">Email</label>
          <input v-model="form.email" type="email" required class="field" placeholder="nome@agenzia.com" />
        </div>

        <div v-if="mode === 'login'">
          <label class="label">Password</label>
          <input v-model="form.password" type="password" required class="field" placeholder="••••••••" />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        <p v-if="notice" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ notice }}</p>

        <button class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Attendere…' : mode === 'login' ? 'Accedi' : 'Invia richiesta' }}
        </button>
      </form>

      <div class="mt-5 flex flex-col items-center gap-2 text-center">
        <button
          class="btn-link"
          @click="((mode = mode === 'login' ? 'forgot' : 'login'), (error = ''), (notice = ''))"
        >
          {{ mode === 'login' ? 'Password dimenticata?' : 'Torna al login' }}
        </button>
        <RouterLink to="/register-b2b" class="text-sm font-semibold text-zinc-500 hover:text-ink">
          Nuovo cliente? Registrati come B2B
        </RouterLink>
      </div>
    </div>
  </div>
</template>
