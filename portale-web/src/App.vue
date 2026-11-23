<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/stores/auth'
import { useConfig } from '@/stores/config'
import AppNav from '@/components/AppNav.vue'

const route = useRoute()
const { isAuthenticated } = useAuth()
const { load: loadConfig, state: cfgState } = useConfig()
const booting = ref(true)

const showChrome = computed(() => isAuthenticated.value && !route.meta.public)

onMounted(async () => {
  if (isAuthenticated.value && !cfgState.loaded) {
    try {
      await loadConfig()
    } catch {
      /* config non critica al boot */
    }
  }
  booting.value = false
})
</script>

<template>
  <div class="min-h-screen">
    <AppNav v-if="showChrome" />
    <main :class="showChrome ? 'mx-auto max-w-6xl px-4 py-6 sm:py-8' : ''">
      <RouterView v-if="!booting" />
    </main>
    <footer v-if="showChrome" class="mx-auto max-w-6xl px-4 pb-8 text-center text-xs text-zinc-400">
      <RouterLink to="/privacy" class="underline">Privacy e cookie</RouterLink> ·
      <RouterLink to="/condizioni" class="underline">Condizioni d'uso</RouterLink>
    </footer>
  </div>
</template>
