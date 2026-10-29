import { createApp } from 'vue'
import router from '@/router'
import { setUnauthorizedHandler } from '@/lib/api'
import { useAuth } from '@/stores/auth'
import App from '@/App.vue'
// font servito dal nostro server (non da Google: niente indirizzo IP dei visitatori inviato a terzi)
import '@fontsource/outfit/latin-300.css'
import '@fontsource/outfit/latin-400.css'
import '@fontsource/outfit/latin-500.css'
import '@fontsource/outfit/latin-600.css'
import '@fontsource/outfit/latin-700.css'
import './style.css'

// al 401 l'interceptor axios ha già ripulito il token: qui portiamo al login
setUnauthorizedHandler(() => {
  // azzera anche lo stato in memoria, altrimenti la guardia del router rimanda dal login alla dashboard
  useAuth().clear()
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

createApp(App).use(router).mount('#app')
