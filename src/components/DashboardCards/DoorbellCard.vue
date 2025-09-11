<template>
  <v-card class="doorbell-card">
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-2" color="primary">mdi-doorbell-video</v-icon>
      ESP32-CAM Deep Sleep Doorbell
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
      <!-- System Status -->
      <div class="mb-4">
        <v-alert 
          :type="isOnline ? 'success' : 'error'"
          variant="tonal"
          density="compact"
        >
          {{ systemStatus }}
        </v-alert>
      </div>

      <!-- Aktionen -->
      <div class="mb-4">
        <v-btn
          @click="refreshStatus"
          :loading="statusLoading"
          color="primary"
          variant="outlined"
          prepend-icon="mdi-refresh"
          class="mr-3"
        >
          Status aktualisieren
        </v-btn>

        <v-btn
          @click="openLiveImageModal"
          :loading="liveImageLoading"
          color="success"
          variant="outlined"
          prepend-icon="mdi-camera"
          class="mr-3"
        >
          Live Bild
        </v-btn>

        <v-btn
          @click="openDetectionsModal"
          :loading="detectionsLoading"
          color="info"
          variant="elevated"
          prepend-icon="mdi-history"
        >
          Ereignisse anzeigen ({{ detectionCount }})
        </v-btn>
      </div>

      <!-- Letzte Erkennung -->
      <div v-if="lastDetection" class="mb-4">
        <v-card variant="outlined" :color="lastDetection.person_detected ? 'warning' : 'success'">
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon 
                :color="lastDetection.person_detected ? 'warning' : 'success'" 
                class="mr-2"
                size="large"
              >
                {{ lastDetection.person_detected ? 'mdi-account-alert' : 'mdi-motion-sensor' }}
              </v-icon>
              <div>
                <div class="font-weight-bold">
                  {{ lastDetection.person_detected ? 'Person erkannt!' : 'Nur Bewegung' }}
                </div>
                <div class="text-caption">
                  {{ formatDateTime(lastDetection.timestamp) }} | 
                  Confidence: {{ (lastDetection.confidence * 100).toFixed(1) }}% |
                  {{ lastDetection.image_count }} Bilder
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>
    </v-card-text>

    <!-- Live Image Modal -->
    <v-dialog v-model="showLiveImageModal" max-width="800">
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-icon class="mr-2" color="success">mdi-camera</v-icon>
          Live Bild
          <v-spacer></v-spacer>
          <v-chip v-if="liveImageTime" size="small" color="info" class="mr-3">
            {{ liveImageTime }}
          </v-chip>
          <v-btn @click="showLiveImageModal = false" icon="mdi-close" variant="text"></v-btn>
        </v-card-title>

        <v-card-text>
          <div v-if="liveImageLoading" class="text-center py-8">
            <v-progress-circular indeterminate color="success"></v-progress-circular>
            <p class="mt-2">Lade Live Bild...</p>
          </div>

          <div v-else-if="!liveImageUrl" class="text-center py-8">
            <v-icon size="64" color="grey">mdi-camera-off</v-icon>
            <p class="text-h6 text-grey mt-2">Kein Live Bild verfügbar</p>
          </div>

          <div v-else>
            <v-img
              :src="liveImageUrl"
              aspect-ratio="4/3"
              cover
              class="live-image"
              :key="liveImageKey"
            >
              <template v-slot:placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <v-progress-circular indeterminate color="success"></v-progress-circular>
                </div>
              </template>
              <template v-slot:error>
                <div class="d-flex align-center justify-center fill-height">
                  <v-icon size="64" color="grey">mdi-image-broken-variant</v-icon>
                  <p class="mt-2">Bild konnte nicht geladen werden</p>
                </div>
              </template>
            </v-img>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-btn 
            @click="refreshLiveImage" 
            :loading="liveImageLoading" 
            color="success" 
            variant="outlined"
            prepend-icon="mdi-refresh"
          >
            Aktualisieren
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn @click="showLiveImageModal = false" color="primary">
            Schließen
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Detections Modal -->
    <v-dialog v-model="showDetectionsModal" max-width="1200" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-icon class="mr-2">mdi-history</v-icon>
          Erkennungs-Ereignisse (Letzte 50)
          <v-spacer></v-spacer>
          <v-btn @click="showDetectionsModal = false" icon="mdi-close" variant="text"></v-btn>
        </v-card-title>

        <v-card-text>
          <div v-if="detectionsLoading" class="text-center py-8">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <p class="mt-2">Lade Ereignisse...</p>
          </div>

          <div v-else-if="detections.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey-lighten-1">mdi-motion-sensor-off</v-icon>
            <p class="text-h6 text-grey-lighten-1 mt-2">Keine Ereignisse</p>
            <p class="text-body-2 text-grey-lighten-1">Noch keine Bewegung oder Personen erkannt</p>
          </div>

          <div v-else class="detections-list">
            <v-row>
              <v-col 
                v-for="detection in detections" 
                :key="detection.id"
                cols="12" md="6" lg="4"
              >
                <v-card 
                  variant="outlined" 
                  :color="detection.person_detected ? 'warning' : 'surface'"
                  @click="selectDetection(detection)"
                  class="detection-card"
                >
                  <v-card-text>
                    <div class="d-flex align-center mb-2">
                      <v-icon 
                        :color="detection.person_detected ? 'warning' : 'success'" 
                        class="mr-2"
                      >
                        {{ detection.person_detected ? 'mdi-account-alert' : 'mdi-motion-sensor' }}
                      </v-icon>
                      <div class="flex-grow-1">
                        <div class="font-weight-bold">
                          {{ detection.person_detected ? 'Person erkannt' : 'Bewegung' }}
                        </div>
                        <div class="text-caption">
                          {{ formatDateTime(detection.timestamp) }}
                        </div>
                      </div>
                      <v-chip size="small" :color="detection.person_detected ? 'warning' : 'success'">
                        {{ (detection.confidence * 100).toFixed(0) }}%
                      </v-chip>
                    </div>
                    
                    <div class="text-caption">
                      <strong>Typ:</strong> {{ getDetectionTypeLabel(detection.detection_type) }}<br>
                      <strong>Bilder:</strong> {{ detection.image_count }}
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="refreshDetections" :loading="detectionsLoading" color="primary" variant="outlined">
            Aktualisieren
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn @click="showDetectionsModal = false" color="primary">
            Schließen
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Detection Images Modal -->
    <v-dialog v-model="showImagesModal" max-width="800">
      <v-card v-if="selectedDetection">
        <v-card-title class="d-flex align-center">
          <v-icon class="mr-2">mdi-image-multiple</v-icon>
          Ereignis vom {{ formatDateTime(selectedDetection.timestamp) }}
          <v-spacer></v-spacer>
          <v-btn @click="showImagesModal = false" icon="mdi-close" variant="text"></v-btn>
        </v-card-title>

        <v-card-text>
          <div v-if="loadingImages" class="text-center py-8">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <p class="mt-2">Lade Bilder...</p>
          </div>

          <div v-else-if="detectionImages.length === 0" class="text-center py-4">
            <p>Keine Bilder verfügbar</p>
          </div>

          <v-carousel
            v-else
            height="400"
            show-arrows
            cycle
            interval="3000"
          >
            <v-carousel-item
              v-for="(image, index) in detectionImages"
              :key="index"
            >
              <img 
                :src="getImageUrl(image)"
                :alt="`Detection Bild ${index + 1}`"
                class="detection-image"
                @error="handleImageError"
              />
              <div class="image-overlay">
                <v-chip color="black" class="text-white">
                  {{ index + 1 }} / {{ detectionImages.length }}
                </v-chip>
              </div>
            </v-carousel-item>
          </v-carousel>

          <div class="mt-4">
            <v-row>
              <v-col cols="12" md="6">
                <v-chip prepend-icon="mdi-clock" color="primary" variant="outlined">
                  {{ formatDateTime(selectedDetection.timestamp) }}
                </v-chip>
              </v-col>
              <v-col cols="12" md="6">
                <v-chip 
                  prepend-icon="mdi-target" 
                  :color="selectedDetection.person_detected ? 'warning' : 'success'" 
                  variant="outlined"
                >
                  {{ (selectedDetection.confidence * 100).toFixed(1) }}% Confidence
                </v-chip>
              </v-col>
            </v-row>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// System Status
const isOnline = ref(false)
const statusLoading = ref(false)
const detectionCount = ref(0)

// Live Image
const liveImageUrl = ref('')
const liveImageTime = ref('')
const liveImageLoading = ref(false)
const liveImageKey = ref(0) // Force image refresh
const showLiveImageModal = ref(false)

// Detections
const detections = ref([])
const detectionsLoading = ref(false)
const showDetectionsModal = ref(false)

// Images
const selectedDetection = ref(null)
const detectionImages = ref([])
const showImagesModal = ref(false)
const loadingImages = ref(false)

// Update intervals
let statusInterval = null

const systemStatus = computed(() => {
  if (!isOnline.value) return 'System offline'
  return `System aktiv | ${detectionCount.value} Ereignisse`
})

const lastDetection = computed(() => {
  return detections.value.length > 0 ? detections.value[0] : null
})

async function loadStatus() {
  try {
    const response = await fetch('/iobroker/api/doorbell.php?endpoint=status')
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    
    const data = await response.json()
    isOnline.value = Boolean(data.online)
    detectionCount.value = data.detections_count || 0
    
  } catch (error) {
    console.error('Status laden fehlgeschlagen:', error)
    isOnline.value = false
  }
}

async function loadLiveImage() {
  try {
    const timestamp = Date.now()
    liveImageUrl.value = `/iobroker/api/doorbell.php?endpoint=live-image&_t=${timestamp}`
    liveImageTime.value = new Date().toLocaleTimeString('de-DE')
    liveImageKey.value = timestamp // Force image component refresh
  } catch (error) {
    console.error('Live image laden fehlgeschlagen:', error)
    liveImageUrl.value = ''
  }
}

async function refreshStatus() {
  statusLoading.value = true
  await loadStatus()
  statusLoading.value = false
}

async function refreshLiveImage() {
  liveImageLoading.value = true
  await loadLiveImage()
  liveImageLoading.value = false
}

async function openLiveImageModal() {
  showLiveImageModal.value = true
  if (!liveImageUrl.value) {
    await refreshLiveImage()
  }
}

async function loadDetections() {
  detectionsLoading.value = true
  try {
    const response = await fetch('/iobroker/api/doorbell.php?endpoint=detections&limit=50')
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    
    const data = await response.json()
    detections.value = Array.isArray(data.detections) ? data.detections : []
    
  } catch (error) {
    console.error('Detections laden fehlgeschlagen:', error)
    detections.value = []
  } finally {
    detectionsLoading.value = false
  }
}

async function refreshDetections() {
  await loadDetections()
}

async function openDetectionsModal() {
  showDetectionsModal.value = true
  if (detections.value.length === 0) {
    await loadDetections()
  }
}

async function selectDetection(detection) {
  selectedDetection.value = detection
  loadingImages.value = true
  showImagesModal.value = true
  
  try {
    const response = await fetch(`/iobroker/api/doorbell.php?endpoint=detections/${detection.id}/images`)
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    
    const data = await response.json()
    detectionImages.value = Array.isArray(data.images) ? data.images : []
    
  } catch (error) {
    console.error('Detection images laden fehlgeschlagen:', error)
    detectionImages.value = []
  } finally {
    loadingImages.value = false
  }
}

function getImageUrl(imagePath) {
  return `/iobroker/api/doorbell.php?endpoint=images/${imagePath}`
}

function getDetectionTypeLabel(type) {
  switch (type) {
    case 'motion': return 'Bewegungserkennung'
    case 'live_image': return 'Live Bild'
    default: return type
  }
}

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
    return 'Ungültiges Datum'
  }
}

function handleImageError(event) {
  console.error('Bild konnte nicht geladen werden:', event.target.src)
}

onMounted(() => {
  loadStatus()
  // Live Image wird nur bei Bedarf geladen (im Modal)
  statusInterval = setInterval(() => {
    loadStatus()
  }, 30000) // Status alle 30 Sekunden
})

onUnmounted(() => {
  if (statusInterval) {
    clearInterval(statusInterval)
  }
})
</script>

<style scoped>
.doorbell-card {
  height: 100%;
}

.live-image {
  border-radius: 8px;
}

.detections-list {
  max-height: 600px;
  overflow-y: auto;
}

.detection-card {
  cursor: pointer;
  transition: all 0.2s ease;
  height: 100%;
}

.detection-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.detection-image {
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

@media (max-width: 600px) {
  .detections-list {
    max-height: 400px;
  }
}
</style>