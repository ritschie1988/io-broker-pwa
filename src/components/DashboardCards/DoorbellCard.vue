<template>
  <v-card class="doorbell-card">
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-2" color="primary">mdi-doorbell-video</v-icon>
      Türklingel {{ roomName }}
      <v-spacer></v-spacer>
      <v-chip 
        :color="isOnline ? 'success' : 'error'" 
        variant="flat" 
        size="small"
      >
        <v-icon start size="small">mdi-circle</v-icon>
        {{ isOnline ? 'Online' : 'Offline' }}
      </v-chip>
    </v-card-title>

    <v-card-text>
      <!-- Live Stream Section -->
      <div class="mb-6">
        <v-btn
          @click="toggleLiveStream"
          :color="showLiveStream ? 'error' : 'primary'"
          :prepend-icon="showLiveStream ? 'mdi-stop' : 'mdi-play'"
          block
          variant="elevated"
          size="large"
          class="mb-3"
        >
          {{ showLiveStream ? 'Live Stream stoppen' : 'Live Stream starten' }}
        </v-btn>

        <!-- Live Stream Display -->
        <div v-if="showLiveStream" class="live-stream-container">
          <img 
            v-if="liveStreamUrl"
            :src="`${liveStreamUrl}&t=${streamImageKey}`"
            alt="Live Stream"
            class="live-stream-image"
            @error="handleStreamError"
            @load="handleStreamLoad"
          />
          <div v-else class="stream-loading">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <p class="mt-2">Stream wird geladen...</p>
          </div>
        </div>
      </div>

      <v-divider class="mb-4"></v-divider>

      <!-- Alarm Settings Section -->
      <div class="mb-6">
        <v-btn
          @click="showAlarmSettings = !showAlarmSettings"
          :prepend-icon="showAlarmSettings ? 'mdi-chevron-up' : 'mdi-chevron-down'"
          variant="outlined"
          block
          class="mb-3"
        >
          Alarm-Einstellungen
        </v-btn>

        <v-expand-transition>
          <v-card v-if="showAlarmSettings" variant="outlined" class="pa-4">
            <div class="d-flex align-center mb-3">
              <v-switch
                v-model="alarmSettings.enabled"
                @update:model-value="updateAlarmSettings"
                color="primary"
                label="Personenerkennung aktiviert"
                hide-details
              ></v-switch>
            </div>

            <div v-if="alarmSettings.enabled">
              <v-row>
                <v-col cols="12" md="6">
                  <v-slider
                    v-model="alarmSettings.sensitivity"
                    @update:model-value="updateAlarmSettings"
                    label="Empfindlichkeit"
                    min="1"
                    max="10"
                    step="1"
                    thumb-label
                    color="primary"
                  ></v-slider>
                </v-col>
                <v-col cols="12" md="6">
                  <v-slider
                    v-model="alarmSettings.captureDelay"
                    @update:model-value="updateAlarmSettings"
                    label="Aufnahme-Dauer (Sekunden)"
                    min="3"
                    max="15"
                    step="1"
                    thumb-label
                    color="primary"
                  ></v-slider>
                </v-col>
              </v-row>

              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="alarmSettings.pushToken"
                    @blur="updateAlarmSettings"
                    label="Push-Benachrichtigung Token"
                    prepend-inner-icon="mdi-bell-ring"
                    variant="outlined"
                    density="compact"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-btn 
                    @click="testAlarm"
                    :loading="testingAlarm"
                    color="warning"
                    variant="outlined"
                    prepend-icon="mdi-test-tube"
                  >
                    Test Alarm
                  </v-btn>
                </v-col>
              </v-row>
            </div>
          </v-card>
        </v-expand-transition>
      </div>

      <v-divider class="mb-4"></v-divider>

      <!-- Alarm History Section -->
      <div>
        <div class="d-flex align-center mb-3">
          <h3 class="text-h6">Alarm Verlauf</h3>
          <v-spacer></v-spacer>
          <v-text-field
            v-model="searchDate"
            @input="filterAlarms"
            label="Datum suchen (YYYY-MM-DD)"
            prepend-inner-icon="mdi-calendar-search"
            variant="outlined"
            density="compact"
            style="max-width: 250px;"
            clearable
          ></v-text-field>
        </div>

        <div class="alarm-list">
          <v-card 
            v-for="alarm in filteredAlarms" 
            :key="alarm.id"
            variant="outlined"
            class="mb-3 alarm-item"
            @click="selectAlarm(alarm)"
          >
            <v-card-text class="d-flex align-center">
              <div class="alarm-info flex-grow-1">
                <div class="d-flex align-center mb-1">
                  <v-icon color="warning" class="mr-2">mdi-account-alert</v-icon>
                  <span class="font-weight-bold">{{ formatDate(alarm.timestamp) }}</span>
                  <v-spacer></v-spacer>
                  <v-chip size="small" color="primary">{{ alarm.imageCount }} Bilder</v-chip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  Confidence: {{ (alarm.confidence * 100).toFixed(1) }}% | 
                  {{ formatTime(alarm.timestamp) }}
                </div>
              </div>
              <v-btn
                @click.stop="viewAlarmImages(alarm)"
                icon="mdi-image-multiple"
                variant="text"
                color="primary"
              ></v-btn>
            </v-card-text>
          </v-card>

          <!-- Empty State -->
          <div v-if="filteredAlarms.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey-lighten-1">mdi-calendar-remove</v-icon>
            <p class="text-h6 text-grey-lighten-1 mt-2">Keine Alarme gefunden</p>
            <p class="text-body-2 text-grey-lighten-1">
              {{ searchDate ? 'Für das gewählte Datum' : 'Noch keine Bewegung erkannt' }}
            </p>
          </div>

          <!-- Load More Button -->
          <div v-if="hasMoreAlarms" class="text-center mt-4">
            <v-btn 
              @click="loadMoreAlarms"
              :loading="loadingMore"
              color="primary"
              variant="outlined"
            >
              Weitere laden
            </v-btn>
          </div>
        </div>
      </div>
    </v-card-text>

    <!-- Image Viewer Dialog -->
    <v-dialog v-model="showImageDialog" max-width="800">
      <v-card v-if="selectedAlarm">
        <v-card-title class="d-flex align-center">
          <v-icon class="mr-2">mdi-image-multiple</v-icon>
          Alarm vom {{ formatDate(selectedAlarm.timestamp) }}
          <v-spacer></v-spacer>
          <v-btn @click="showImageDialog = false" icon="mdi-close" variant="text"></v-btn>
        </v-card-title>

        <v-card-text>
          <v-carousel
            v-if="selectedAlarm.images && selectedAlarm.images.length > 0"
            height="400"
            show-arrows
            cycle
          >
            <v-carousel-item
              v-for="(image, index) in selectedAlarm.images"
              :key="index"
            >
              <img 
                :src="getImageUrl(image)"
                :alt="`Alarm Bild ${index + 1}`"
                class="alarm-image"
              />
              <div class="image-overlay">
                <v-chip color="black" class="text-white">
                  {{ index + 1 }} / {{ selectedAlarm.images.length }}
                </v-chip>
              </div>
            </v-carousel-item>
          </v-carousel>

          <div class="mt-4">
            <v-row>
              <v-col cols="6">
                <v-chip prepend-icon="mdi-clock" color="primary" variant="outlined">
                  {{ formatDateTime(selectedAlarm.timestamp) }}
                </v-chip>
              </v-col>
              <v-col cols="6" class="text-right">
                <v-btn 
                  @click="downloadAlarmImages(selectedAlarm)"
                  color="primary"
                  prepend-icon="mdi-download"
                  variant="outlined"
                >
                  Herunterladen
                </v-btn>
              </v-col>
            </v-row>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({ 
  room: {
    type: String,
    required: true
  }
})

// Raumname für die Card-Überschrift
const roomName = computed(() => {
  switch (props.room) {
    case 'vorraum': return 'Eingang'
    case 'haustuer': return 'Haustür'
    default: return 'Bereich'
  }
})

// System Status
const isOnline = ref(false)
const systemStatus = ref({})

// Live Stream
const showLiveStream = ref(false)
const liveStreamUrl = ref('')
const streamError = ref(false)
const streamRefreshInterval = ref(null)
const streamImageKey = ref(0)  // Force refresh

// Alarm Settings
const showAlarmSettings = ref(false)
const alarmSettings = ref({
  enabled: true,
  sensitivity: 7,
  captureDelay: 5,
  pushToken: ''
})

// Test Alarm
const testingAlarm = ref(false)

// Alarm History
const alarms = ref([])
const filteredAlarms = ref([])
const searchDate = ref('')
const loadingMore = ref(false)
const hasMoreAlarms = ref(true)
const currentPage = ref(1)

// Image Dialog
const showImageDialog = ref(false)
const selectedAlarm = ref(null)

// Update intervals
let statusInterval = null
let streamCheckInterval = null

/**
 * System Status laden
 */
async function loadStatus() {
  try {
    const response = await fetch('/iobroker/api/doorbell.php?endpoint=status')
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    const data = await response.json()
    
    isOnline.value = Boolean(data.online)
    systemStatus.value = data
    
    if (data.settings) {
      Object.assign(alarmSettings.value, data.settings)
    }
  } catch (error) {
    console.error('Fehler beim Laden des Status:', error)
    isOnline.value = false
  }
}

/**
 * Live Stream starten/stoppen
 */
async function toggleLiveStream() {
  if (showLiveStream.value) {
    // Stream stoppen
    showLiveStream.value = false
    liveStreamUrl.value = ''
    
    // Auto-refresh stoppen
    if (streamRefreshInterval.value) {
      clearInterval(streamRefreshInterval.value)
      streamRefreshInterval.value = null
    }
    
    try {
      const response = await fetch('/iobroker/api/doorbell.php?endpoint=stream/stop', { method: 'POST' })
      if (!response.ok) {
        console.warn('Stream stop request failed:', response.status)
      }
    } catch (error) {
      console.error('Fehler beim Stoppen des Streams:', error)
    }
  } else {
    // Stream starten
    showLiveStream.value = true
    streamError.value = false
    
    try {
      const response = await fetch('/iobroker/api/doorbell.php?endpoint=stream/start', { method: 'POST' })
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      const data = await response.json()
      
      if (data.success && data.streamUrl) {
        liveStreamUrl.value = data.streamUrl
        
        // Für MJPEG Video-Stream kein Timer nötig - Stream läuft kontinuierlich
        streamImageKey.value = Date.now()  // Einmaliger Cache-Buster
        
      } else {
        throw new Error(data.error || 'Stream konnte nicht gestartet werden')
      }
    } catch (error) {
      console.error('Fehler beim Starten des Streams:', error)
      showLiveStream.value = false
      streamError.value = true
    }
  }
}

/**
 * Stream Fehler behandeln
 */
function handleStreamError() {
  streamError.value = true
  console.error('Live Stream Fehler')
}

/**
 * Stream erfolgreich geladen
 */
function handleStreamLoad() {
  streamError.value = false
}

/**
 * Alarm-Einstellungen aktualisieren
 */
async function updateAlarmSettings() {
  try {
    const response = await fetch('/iobroker/api/doorbell.php?endpoint=settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(alarmSettings.value)
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    console.error('Fehler beim Speichern der Einstellungen:', error)
  }
}

/**
 * Test-Alarm auslösen
 */
async function testAlarm() {
  testingAlarm.value = true
  
  try {
    const response = await fetch('/iobroker/api/doorbell.php?endpoint=test-alarm', { method: 'POST' })
    const data = await response.json()
    
    if (data.success) {
      // Kurz warten und dann Alarme neu laden
      setTimeout(loadAlarms, 2000)
    }
  } catch (error) {
    console.error('Fehler beim Test-Alarm:', error)
  } finally {
    testingAlarm.value = false
  }
}

/**
 * Alarme laden
 */
async function loadAlarms(page = 1, append = false) {
  try {
    const response = await fetch(`/iobroker/api/doorbell.php?endpoint=alarms&page=${page}&limit=10`)
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    const data = await response.json()
    
    if (append && Array.isArray(alarms.value) && Array.isArray(data.alarms)) {
      alarms.value = [...alarms.value, ...data.alarms]
    } else {
      alarms.value = Array.isArray(data.alarms) ? data.alarms : []
    }
    
    hasMoreAlarms.value = Boolean(data.hasMore)
    currentPage.value = page
    
    filterAlarms()
  } catch (error) {
    console.error('Fehler beim Laden der Alarme:', error)
    alarms.value = []
    filteredAlarms.value = []
  }
}

/**
 * Weitere Alarme laden
 */
async function loadMoreAlarms() {
  loadingMore.value = true
  await loadAlarms(currentPage.value + 1, true)
  loadingMore.value = false
}

/**
 * Alarme nach Datum filtern
 */
function filterAlarms() {
  if (!Array.isArray(alarms.value)) {
    filteredAlarms.value = []
    return
  }
  
  if (!searchDate.value || searchDate.value.trim() === '') {
    filteredAlarms.value = alarms.value
    return
  }
  
  try {
    const searchTimestamp = new Date(searchDate.value).toISOString().split('T')[0]
    filteredAlarms.value = alarms.value.filter(alarm => {
      if (!alarm || !alarm.timestamp) return false
      
      try {
        const alarmDate = new Date(alarm.timestamp).toISOString().split('T')[0]
        return alarmDate === searchTimestamp
      } catch (e) {
        return false
      }
    })
  } catch (error) {
    console.error('Fehler beim Filtern der Alarme:', error)
    filteredAlarms.value = alarms.value
  }
}

/**
 * Alarm auswählen
 */
function selectAlarm(alarm) {
  selectedAlarm.value = alarm
  loadAlarmImages(alarm)
}

/**
 * Alarm-Bilder laden
 */
async function loadAlarmImages(alarm) {
  try {
    const response = await fetch(`/iobroker/api/doorbell.php?endpoint=alarms/${alarm.id}/images`)
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    const data = await response.json()
    
    if (selectedAlarm.value && selectedAlarm.value.id === alarm.id) {
      selectedAlarm.value.images = Array.isArray(data.images) ? data.images : []
      showImageDialog.value = true
    }
  } catch (error) {
    console.error('Fehler beim Laden der Alarm-Bilder:', error)
  }
}

/**
 * Alarm-Bilder anzeigen
 */
function viewAlarmImages(alarm) {
  selectAlarm(alarm)
}

/**
 * Bild-URL generieren
 */
function getImageUrl(imagePath) {
  return `/iobroker/api/doorbell.php?endpoint=images/${imagePath}`
}

/**
 * Alarm-Bilder herunterladen
 */
async function downloadAlarmImages(alarm) {
  if (!alarm || !alarm.id) {
    console.error('Ungültiger Alarm für Download')
    return
  }
  
  try {
    const response = await fetch(`/iobroker/api/doorbell.php?endpoint=alarms/${alarm.id}/download`)
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
    
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `alarm_${formatDate(alarm.timestamp)}.zip`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Fehler beim Herunterladen der Bilder:', error)
  }
}

/**
 * Datum formatieren
 */
function formatDate(timestamp) {
  if (!timestamp) return 'Unbekannt'
  
  try {
    return new Date(timestamp).toLocaleDateString('de-DE', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    })
  } catch (error) {
    console.error('Fehler beim Formatieren des Datums:', error)
    return 'Ungültiges Datum'
  }
}

/**
 * Zeit formatieren
 */
function formatTime(timestamp) {
  if (!timestamp) return 'Unbekannt'
  
  try {
    return new Date(timestamp).toLocaleTimeString('de-DE', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  } catch (error) {
    console.error('Fehler beim Formatieren der Zeit:', error)
    return 'Ungültige Zeit'
  }
}

/**
 * Datum und Zeit formatieren
 */
function formatDateTime(timestamp) {
  if (!timestamp) return 'Unbekannt'
  
  try {
    return new Date(timestamp).toLocaleString('de-DE', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  } catch (error) {
    console.error('Fehler beim Formatieren von Datum/Zeit:', error)
    return 'Ungültiges Datum/Zeit'
  }
}

// Lifecycle
onMounted(() => {
  loadStatus()
  loadAlarms()
  
  // Regelmäßige Status-Updates
  statusInterval = setInterval(loadStatus, 10000) // alle 10 Sekunden
  
  // Stream-Check
  streamCheckInterval = setInterval(() => {
    if (showLiveStream.value && streamError.value) {
      // Versuche Stream neu zu starten bei Fehler
      toggleLiveStream()
    }
  }, 30000) // alle 30 Sekunden
})

onUnmounted(() => {
  if (statusInterval) {
    clearInterval(statusInterval)
  }
  if (streamCheckInterval) {
    clearInterval(streamCheckInterval)
  }
  if (streamRefreshInterval.value) {
    clearInterval(streamRefreshInterval.value)
  }
  
  // Stream stoppen beim Verlassen der Seite
  if (showLiveStream.value) {
    toggleLiveStream()
  }
})
</script>

<style scoped>
.doorbell-card {
  height: 100%;
}

.live-stream-container {
  border-radius: 12px;
  overflow: hidden;
  background: #f5f5f5;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  max-height: 500px; /* Container-Höhe erhöhen */
}

.live-stream-image {
  width: 100%;
  height: auto;
  max-height: 480px; /* Bild-Höhe erhöhen */
  object-fit: contain;
  border-radius: 8px;
}

.stream-loading {
  text-align: center;
  padding: 40px 20px;
  color: #666;
}

.alarm-list {
  max-height: 400px;
  overflow-y: auto;
}

.alarm-item {
  cursor: pointer;
  transition: all 0.2s ease;
}

.alarm-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.alarm-info {
  min-width: 0; /* Allow text to wrap */
}

.alarm-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.image-overlay {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 1;
}

/* Responsive */
@media (max-width: 600px) {
  .live-stream-container {
    max-height: 350px;
  }
  
  .live-stream-image {
    max-height: 320px;
  }
  
  .alarm-list {
    max-height: 300px;
  }
}
</style>