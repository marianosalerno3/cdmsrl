import { createApp } from 'vue'
import router from '@/router'
import { setUnauthorizedHandler } from '@/lib/api'
import App from '@/App.vue'
import './style.css'

// al 401 l'interceptor axios ha già ripulito il token: qui portiamo al login
setUnauthorizedHandler(() => {
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

createApp(App).use(router).mount('#app')
