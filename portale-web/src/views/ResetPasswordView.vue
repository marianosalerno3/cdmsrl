<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { COMPANY_NAME } from '@/lib/brand'

// arrivo dal link dell'email: /reset-password?token=…&email=…
const route = useRoute()
const token = computed(() => String(route.query.token || ''))
const email = computed(() => String(route.query.email || ''))
const linkValido = computed(() => !!token.value && !!email.value)

const password = ref('')
const conferma = ref('')
const loading = ref(false)
const error = ref('')
const done = ref(false)

async function submit() {
  error.value = ''
  if (password.value !== conferma.value) {
    error.value = 'Le due password non coincidono.'
    return
  }
  loading.value = true
  try {
    await api.post('/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: conferma.value,
    })
    done.value = true
  } catch (e) {
    error.value = errorMessage(e, 'Non è stato possibile reimpostare la password.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen place-items-center p-4">
    <div class="card w-full max-w-md p-8">
      <p class="text-center text-3xl font-extrabold uppercase tracking-tight">{{ COMPANY_NAME }}</p>
      <h1 class="mt-1 text-center text-sm font-medium uppercase tracking-widest text-zinc-500">Portale B2B</h1>

      <div v-if="done" class="mt-6 text-center">
        <p class="text-lg font-bold">Password aggiornata</p>
        <p class="mt-1 text-sm text-zinc-500">Ora puoi accedere con la nuova password.</p>
        <RouterLink to="/login" class="btn-primary mt-6 w-full">Vai all'accesso</RouterLink>
      </div>

      <div v-else-if="!linkValido" class="mt-6 text-center">
        <p class="text-sm text-red-700">Il link non è completo. Richiedine uno nuovo dalla pagina di accesso.</p>
        <RouterLink to="/login" class="btn-link mt-4">Torna all'accesso</RouterLink>
      </div>

      <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
        <p class="text-center text-sm text-zinc-500">
          Scegli una nuova password per <span class="font-semibold text-ink">{{ email }}</span>
        </p>
        <div>
          <label class="label">Nuova password (min. 8 caratteri)</label>
          <input
            v-model="password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="field"
          />
        </div>
        <div>
          <label class="label">Conferma password</label>
          <input
            v-model="conferma"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="field"
          />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <button class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Attendere…' : 'Salva nuova password' }}
        </button>
        <div class="text-center">
          <RouterLink to="/login" class="btn-link">Torna all'accesso</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>
