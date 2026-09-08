<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/stores/auth'
import { useCart } from '@/stores/cart'
import { useCustomer } from '@/stores/customer'

const router = useRouter()
const { state: auth, logout } = useAuth()
const { count } = useCart()
const { selected } = useCustomer()
const open = ref(false)

const links = [
  { to: '/dashboard', label: 'Dashboard' },
  { to: '/catalog', label: 'Catalogo' },
  { to: '/sostituzioni', label: 'Sostituzioni' },
  { to: '/agent/new-client', label: 'Nuovo Cliente' },
]

const agentName = computed(() => auth.user?.nome || auth.user?.email || 'Agente')

async function onLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="sticky top-0 z-20 border-b border-black/5 bg-canvas/80 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
      <RouterLink to="/dashboard" class="text-lg font-bold tracking-tight">Portale B2B</RouterLink>

      <nav class="ml-4 hidden items-center gap-1 md:flex">
        <RouterLink
          v-for="l in links"
          :key="l.to"
          :to="l.to"
          class="rounded-full px-3 py-1.5 text-sm font-medium text-zinc-600 hover:bg-dark-glass hover:text-ink"
          active-class="bg-dark-glass text-ink"
        >
          {{ l.label }}
        </RouterLink>
      </nav>

      <div class="ml-auto flex items-center gap-2">
        <span v-if="selected" class="hidden text-xs text-zinc-500 sm:inline">
          Cliente: <span class="font-semibold text-ink">{{ selected.denominazione }}</span>
        </span>

        <RouterLink to="/cart" class="btn-ghost relative">
          Carrello
          <span
            v-if="count"
            class="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-accent text-[11px] font-bold text-white"
          >
            {{ count }}
          </span>
        </RouterLink>

        <div class="hidden items-center gap-2 sm:flex">
          <span class="grid size-8 place-items-center rounded-full bg-dark text-xs font-bold text-white">
            {{ agentName.slice(0, 1).toUpperCase() }}
          </span>
          <button class="btn-link" @click="onLogout">Esci</button>
        </div>

        <button class="btn-ghost md:hidden" @click="open = !open">Menu</button>
      </div>
    </div>

    <div v-if="open" class="border-t border-black/5 px-4 py-2 md:hidden">
      <RouterLink
        v-for="l in links"
        :key="l.to"
        :to="l.to"
        class="block rounded-lg px-2 py-2 text-sm font-medium"
        @click="open = false"
      >
        {{ l.label }}
      </RouterLink>
      <button class="block px-2 py-2 text-sm font-semibold text-accent" @click="onLogout">Esci</button>
    </div>
  </header>
</template>
