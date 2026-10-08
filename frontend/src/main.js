import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

// Registrasi service worker untuk PWA (agar bisa dipasang di beranda smartphone).
// Hanya di build produksi supaya dev server tidak meng-cache apa pun.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      /* registrasi gagal tidak menghalangi aplikasi */
    })
  })
}
