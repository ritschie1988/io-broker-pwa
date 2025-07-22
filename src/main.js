import { createApp, ref } from 'vue'
import App from './App.vue'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'

const vuetify = createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'lightBlue',
    themes: {
      lightBlue: {
        dark: false,
        colors: {
          primary: '#2563eb', // Modernes Blau
          secondary: '#38bdf8', // Helles Blau
          accent: '#1e40af', // Akzent Dunkelblau
          background: '#f4f6fb',
          surface: '#ffffff',
          info: '#0ea5e9',
          success: '#22d3ee',
          warning: '#fbbf24',
          error: '#ef4444',
        },
      },
      darkBlue: {
        dark: true,
        colors: {
          primary: '#60a5fa', // Helles Blau
          secondary: '#2563eb', // Modernes Blau
          accent: '#38bdf8',
          background: '#0f172a',
          surface: '#1e293b',
          info: '#38bdf8',
          success: '#22d3ee',
          warning: '#fbbf24',
          error: '#ef4444',
        },
      },
    },
  },
})

// Für globales Theme-Switching in der App
if (typeof window !== 'undefined') {
  window.$vuetify = vuetify;
}

const view = ref('dashboard')
const navItems = [
  { title: 'Dashboard', view: 'dashboard' },
  { title: 'Räume', view: 'rooms' },
  { title: 'Geräte', view: 'devices' },
  { title: 'Analytics', view: 'analytics' },
  { title: 'Settings', view: 'settings' },
]

createApp(App).use(vuetify).mount('#app')