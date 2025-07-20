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
    defaultTheme: 'myTheme',
    themes: {
      myTheme: {
        dark: false,
        colors: {
          primary: '#2a2e6e', // Dunkelblau
          secondary: '#7c3aed', // Lila
          background: '#f4f6fb',
        },
      },
    },
  },
})

const view = ref('dashboard')
const navItems = [
  { title: 'Dashboard', view: 'dashboard' },
  { title: 'Räume', view: 'rooms' },
  { title: 'Geräte', view: 'devices' },
  { title: 'Analytics', view: 'analytics' },
  { title: 'Settings', view: 'settings' },
]

createApp(App).use(vuetify).mount('#app')