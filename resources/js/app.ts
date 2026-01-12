import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import pinia from './store'

// Import CSS
import '../css/app.css'

// Clear any existing service workers
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(function(registrations) {
    for(let registration of registrations) {
      registration.unregister()
      console.log('Unregistered service worker:', registration.scope)
    }
  })
}

// Clear any cached data that might be causing issues
if ('caches' in window) {
  caches.keys().then(function(names) {
    for (let name of names) {
      caches.delete(name)
      console.log('Cleared cache:', name)
    }
  })
}

// Create Vue app
const app = createApp(App)

// Use plugins
app.use(pinia)
app.use(router)

// Mount app
app.mount('#app')

// Debug log to verify app is loading
console.log('Vue app mounted successfully')
console.log('Environment:', import.meta.env.DEV ? 'development' : 'production')
console.log('Service workers and caches cleared')
console.log('Navigation should work properly now')