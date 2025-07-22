
<template>
  <v-container>
    <v-toolbar class="settings-toolbar" flat>
      <v-slide-group
        v-model="selectedSection"
        class="settings-menu"
        show-arrows
        center-active
        prev-icon="mdi-chevron-left"
        next-icon="mdi-chevron-right"
        mobile-breakpoint="0"
      >
        <v-slide-group-item
          v-for="section in settingsSections"
          :key="section.id"
          :value="section.id"
        >
          <v-btn
            :color="selectedSection === section.id ? 'primary' : 'default'"
            class="mx-1"
            @click="selectedSection = section.id"
            rounded
            text
          >
            {{ section.name }}
          </v-btn>
        </v-slide-group-item>
      </v-slide-group>
    </v-toolbar>
    <v-row class="settings-cards">
      <template v-for="cardName in sectionConfigs[selectedSection]" :key="cardName">
        <v-col cols="12" md="8" lg="6">
          <component
            :is="settingsCardComponents[cardName]"
            v-bind="getCardProps(cardName)"
            v-on="getCardEvents(cardName)"
          />
        </v-col>
      </template>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import GeneralSettingsCard from '../components/SettingsCards/GeneralSettingsCard.vue'
import PVHeizstabSettingsCard from '../components/SettingsCards/PVHeizstabSettingsCard.vue'
import ConnectionSettingsCard from '../components/SettingsCards/ConnectionSettingsCard.vue'
import LoggingSettingsCard from '../components/SettingsCards/LoggingSettingsCard.vue'
import UserSettingsCard from '../components/SettingsCards/UserSettingsCard.vue'

const settingsSections = [
  { id: 'general', name: 'Allgemein' },
  { id: 'pvheizstab', name: 'PV-Heizstab' },
  { id: 'connection', name: 'Verbindung' },
  { id: 'logging', name: 'Logging' },
  { id: 'user', name: 'Benutzer' },
]
const selectedSection = ref(settingsSections[0].id)

const sectionConfigs = {
  general: ['GeneralSettingsCard'],
  pvheizstab: ['PVHeizstabSettingsCard'],
  connection: ['ConnectionSettingsCard'],
  logging: ['LoggingSettingsCard'],
  user: ['UserSettingsCard'],
}

const settingsCardComponents = {
  GeneralSettingsCard,
  PVHeizstabSettingsCard,
  ConnectionSettingsCard,
  LoggingSettingsCard,
  UserSettingsCard,
}


// Props für zentrale Settings
import { defineProps, defineEmits } from 'vue'
const props = defineProps({
  darkTheme: Boolean,
  notifications: Boolean
})
const emit = defineEmits(['update:darkTheme', 'update:notifications'])

// Globale States für allgemeine Settings
const darkTheme = ref(false)
const notifications = ref(false)

// Lokale States für andere Settings
const pvThreshold = ref(3500)
const tempMin = ref(50)
const tempMax = ref(60)
const override = ref(false)

// Override laden
async function loadOverride() {
  try {
    const res = await fetch('/iobroker/api/iobroker-proxy.php?endpoint=get/0_userdata.0.OverrideWarmwasser')
    if (!res.ok) throw new Error('Fehler beim Laden Override')
    const data = await res.json()
    override.value = !!data.val
  } catch (e) {
    // Fehlerbehandlung optional
  }
}

// Override speichern
async function saveOverride() {
  try {
    await fetch('/iobroker/api/iobroker-proxy.php?endpoint=set/0_userdata.0.OverrideWarmwasser?value=' + (override.value ? 'true' : 'false'))
  } catch (e) {
    // Fehlerbehandlung optional
  }
}
const pvSettingsLoading = ref(false)
const pvSettingsError = ref('')

// Settings laden (PV + General)
async function loadSettings() {
  pvSettingsLoading.value = true
  try {
    const res = await fetch('/iobroker/api/settings.php')
    if (!res.ok) throw new Error('Fehler beim Laden der Settings')
    const data = await res.json()
    // PV
    const pv = data.pv_settings || {}
    pvThreshold.value = pv.pvThreshold ?? 3500
    tempMin.value = pv.tempMin ?? 50
    tempMax.value = pv.tempMax ?? 60
    // General
    const gen = data.general || {}
    darkTheme.value = !!gen.darkTheme
    notifications.value = !!gen.notifications
    // Theme direkt setzen (Vuetify 3)
    if (typeof window !== 'undefined' && window.$vuetify) {
      window.$vuetify.theme.global.name.value = darkTheme.value ? 'darkBlue' : 'lightBlue'
    }
  } catch (e) {
    pvSettingsError.value = e.message
  } finally {
    pvSettingsLoading.value = false
  }
}

// Settings speichern (PV + General)
async function saveSettings() {
  try {
    await fetch('/iobroker/api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pvThreshold: pvThreshold.value,
        tempMin: tempMin.value,
        tempMax: tempMax.value,
        darkTheme: darkTheme.value,
        notifications: notifications.value
      })
    })
    // Theme direkt setzen (Vuetify 3)
    if (typeof window !== 'undefined' && window.$vuetify) {
      window.$vuetify.theme.global.name.value = darkTheme.value ? 'darkBlue' : 'lightBlue'
    }
  } catch (e) {
    // Fehlerbehandlung optional
  }
}


// Speichern nur noch per Button

onMounted(() => {
  loadSettings()
  loadOverride()
})
const apiUrl = ref('')
const logLevel = ref('Info')

function showLog() {
  // Log-Datei anzeigen
}
function downloadLog() {
  // Log-Datei herunterladen
}
function changePassword(newPassword) {
  // Passwort ändern
}

// Props und Events je Card
function getCardProps(cardName) {
  switch (cardName) {
    case 'GeneralSettingsCard':
      return { darkTheme: darkTheme.value, notifications: notifications.value }
    case 'PVHeizstabSettingsCard':
      return { pvThreshold: pvThreshold.value, tempMin: tempMin.value, tempMax: tempMax.value, override: override.value, loading: pvSettingsLoading.value, error: pvSettingsError.value }
    case 'ConnectionSettingsCard':
      return { apiUrl: apiUrl.value }
    case 'LoggingSettingsCard':
      return { logLevel: logLevel.value }
    default:
      return {}
  }
}
function getCardEvents(cardName) {
  switch (cardName) {
    case 'GeneralSettingsCard':
      return {
        'update:darkTheme': val => {
          darkTheme.value = val
          saveSettings()
          // Theme direkt setzen (Vuetify 3)
          if (typeof window !== 'undefined' && window.$vuetify) {
            window.$vuetify.theme.global.name.value = val ? 'darkBlue' : 'lightBlue'
          }
        },
        'update:notifications': val => {
          notifications.value = val
          saveSettings()
        },
      }
    case 'PVHeizstabSettingsCard':
      return {
        'update:pvThreshold': val => (pvThreshold.value = val),
        'update:tempMin': val => (tempMin.value = val),
        'update:tempMax': val => (tempMax.value = val),
        'update:override': val => {
          override.value = val
          saveOverride()
        },
        'savePVSettings': saveSettings,
      }
    case 'ConnectionSettingsCard':
      return {
        'update:apiUrl': val => (apiUrl.value = val),
      }
    case 'LoggingSettingsCard':
      return {
        'update:logLevel': val => (logLevel.value = val),
        showLog,
        downloadLog,
      }
    case 'UserSettingsCard':
      return {
        changePassword,
      }
    default:
      return {}
  }
}
</script>

<style scoped>
.settings-menu {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  overflow-x: auto;
  max-width: 100vw;
}
.settings-toolbar {
  flex-wrap: wrap;
  min-height: unset;
  position: sticky;
  top: var(--v-app-bar-height, 56px);
  z-index: 2;
  margin-top: 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.03);
}
.settings-cards {
  margin-top: 2rem;
}
</style>