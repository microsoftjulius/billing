import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import pinia from './store'

// Import CSS
import '../css/app.css'

// Create Vue app
const app = createApp(App)

// Use plugins
app.use(pinia)
app.use(router)

// Mount app
app.mount('#app')