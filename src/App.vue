<template>
  <v-app>
    <template v-if="isAuthenticated">
      <!-- ...existing code... -->
      <v-app-bar app color="primary" dark>
        <v-app-bar-nav-icon @click="drawer = !drawer" />
        <v-toolbar-title>Smart Home</v-toolbar-title>
        <v-spacer />
        <v-btn icon @click="logout" title="Logout">
          <v-icon>mdi-logout</v-icon>
        </v-btn>
        <v-tabs
          v-model="view"
          background-color="primary"
          show-arrows
          class="main-tabs"
          slider-color="secondary"
          dark
        >
          <v-tab
            v-for="item in navItems"
            :key="item.title"
            :value="item.view"
          >
            {{ item.title }}
          </v-tab>
        </v-tabs>
      </v-app-bar>
      <v-navigation-drawer
        v-model="drawer"
        app
        color="primary"
        dark
        :clipped="$vuetify.display.smAndDown"
        temporary
      >
        <v-list>
          <v-list-item
            v-for="item in navItems"
            :key="item.title"
            @click="view = item.view; drawer = false"
            :active="view === item.view"
          >
            <v-list-item-title>{{ item.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-navigation-drawer>
      <v-main>
        <v-container fluid class="pa-0">
          <component
            :is="currentView"
            :selectedRoom="selectedRoom"
            :darkTheme="darkTheme"
            :notifications="notifications"
            @update:darkTheme="onUpdateDarkTheme"
            @update:notifications="onUpdateNotifications"
          />
        </v-container>
      </v-main>
    </template>
    <template v-else>
      <Login @login-success="onLoginSuccess" />
    </template>
  </v-app>
</template>

<script setup>
import Login from './components/Login.vue'
const isAuthenticated = ref(false)

function parseJwt(token) {
  if (!token) return null;
  const parts = token.split('.');
  if (parts.length !== 3) return null;
  try {
    const payload = JSON.parse(atob(parts[1]));
    return payload;
  } catch (e) {
    return null;
  }
}

function checkAuth() {
  const token = localStorage.getItem('auth_token');
  if (!token) {
    isAuthenticated.value = false;
    return;
  }
  const payload = parseJwt(token);
  if (!payload || !payload.exp) {
    isAuthenticated.value = false;
    return;
  }
  const now = Math.floor(Date.now() / 1000);
  if (payload.exp < now) {
    localStorage.removeItem('auth_token');
    isAuthenticated.value = false;
    return;
  }
  isAuthenticated.value = true;
}

onMounted(() => {
  checkAuth();
  // Automatische Prüfung alle 30 Sekunden
  setInterval(checkAuth, 30000);
})

function onLoginSuccess() {
  checkAuth();
  window.location.href = 'https://darkorbithome.ddns.net/iobroker/';
}

function logout() {
  localStorage.removeItem('auth_token');
  isAuthenticated.value = false;
  window.location.reload();
}
import { ref, computed, watch, getCurrentInstance, onMounted } from 'vue'
import DashboardView from './views/DashboardView.vue'
import HeizungView from './views/HeizungView.vue'
import RoomsView from './views/RoomsView.vue'
import DevicesView from './views/DevicesView.vue'
import AnalyticsView from './views/AnalyticsView.vue'
import SettingsView from './views/SettingsView.vue'

const drawer = ref(false)
const view = ref('dashboard')
const navItems = [
  { title: 'Dashboard', view: 'dashboard' },
  { title: 'Heizung', view: 'heizung' },
  { title: 'Räume', view: 'rooms' },
  { title: 'Geräte', view: 'devices' },
  { title: 'Analytics', view: 'analytics' },
  { title: 'Settings', view: 'settings' },
]

// Zentrale Settings
const darkTheme = ref(false)
const notifications = ref(true)

// Initial aus localStorage lesen
onMounted(() => {
  const storedDark = localStorage.getItem('darkTheme')
  if (storedDark !== null) darkTheme.value = storedDark === 'true'
  const storedNotif = localStorage.getItem('notifications')
  if (storedNotif !== null) notifications.value = storedNotif === 'true'
  setVuetifyTheme(darkTheme.value)
})

// Theme umschalten
let vuetify = null
onMounted(() => {
  const instance = getCurrentInstance()
  vuetify = instance?.proxy?.$vuetify
})
function setVuetifyTheme(isDark) {
  if (vuetify && vuetify.theme && vuetify.theme.global) {
    vuetify.theme.global.name.value = isDark ? 'dark' : 'light'
  } else if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light')
  }
}

// Änderungen speichern und weiterreichen
function onUpdateDarkTheme(val) {
  darkTheme.value = val
  localStorage.setItem('darkTheme', val)
  setVuetifyTheme(val)
}
function onUpdateNotifications(val) {
  notifications.value = val
  localStorage.setItem('notifications', val)
}

// Wenn localStorage sich ändert (z.B. anderer Tab), synchronisieren
window.addEventListener('storage', (e) => {
  if (e.key === 'darkTheme') {
    darkTheme.value = e.newValue === 'true'
    setVuetifyTheme(darkTheme.value)
  }
  if (e.key === 'notifications') {
    notifications.value = e.newValue === 'true'
  }
})

const currentView = computed(() => {
  switch (view.value) {
    case 'dashboard': return DashboardView
    case 'heizung': return HeizungView
    case 'rooms': return RoomsView
    case 'devices': return DevicesView
    case 'analytics': return AnalyticsView
    case 'settings': return SettingsView
    default: return DashboardView
  }
})
</script>

<style scoped>
.toolbar-menu {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.room-tabs {
  max-width: 100%;
}
.main-tabs {
  max-width: 100%;
}
</style>
