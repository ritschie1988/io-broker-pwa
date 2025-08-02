<template>
  <div class="shutter-container">
    <!-- Rollladen Canvas -->
    <canvas 
      ref="canvasRef" 
      class="shutter-canvas"
      @click="handleCanvasClick"
    ></canvas>
    
    <!-- Slider Canvas -->
    <canvas 
      ref="sliderCanvasRef" 
      class="slider-canvas"
      @mousedown="startDragging"
      @mousemove="handleMouseMove"
      @mouseup="stopDragging"
      @mouseleave="stopDragging"
      @touchstart="startDragging"
      @touchmove="handleTouchMove"
      @touchend="stopDragging"
    ></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'

// Props für die Komponente
const props = defineProps({
  position: {
    type: Number,
    default: 0 // 0 = komplett offen, 100 = komplett geschlossen
  },
  width: {
    type: Number,
    default: 300 // Canvas Breite in Pixeln
  }
})

// Emits für Position-Änderungen
const emit = defineEmits(['positionChange'])

// Template Refs für beide Canvas Elemente
const canvasRef = ref(null)
const sliderCanvasRef = ref(null)

// Canvas Dimensionen
const canvasWidth = ref(300)
const canvasHeight = ref(200) // Wird dynamisch berechnet (4:3 Verhältnis)
const sliderWidth = 15
const sliderHeight = computed(() => canvasHeight.value) // Slider hat gleiche Höhe wie Rollladen

// Slider State
const isDragging = ref(false)
const sliderPosition = ref(0) // 0-100

// Animation State
const isAnimating = ref(false)
const animationId = ref(null)
const currentAnimatedPosition = ref(0) // Aktuelle animierte Position
const targetPosition = ref(0) // Ziel-Position
const animationStartTime = ref(0)
const animationDuration = 1500 // Animation-Dauer in ms (1.5 Sekunden)

/**
 * Easing-Funktion für realistische Rollladen-Bewegung
 * Beschleunigt am Anfang, verlangsamt am Ende (wie echter Motor)
 */
function easeInOutCubic(t) {
  return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2
}

/**
 * Animation zu neuer Position starten
 */
function animateToPosition(newPosition) {
  // Vorherige Animation stoppen
  stopAnimation()
  
  // Ziel-Position setzen
  targetPosition.value = newPosition
  
  // Wenn wir schon an der Ziel-Position sind, keine Animation
  if (Math.abs(currentAnimatedPosition.value - newPosition) < 0.5) {
    currentAnimatedPosition.value = newPosition
    drawShutter()
    return
  }
  
  // Animation starten
  isAnimating.value = true
  animationStartTime.value = performance.now()
  
  // Animation-Loop starten
  animationLoop()
}

/**
 * Animation-Loop (60fps mit requestAnimationFrame)
 */
function animationLoop() {
  if (!isAnimating.value) return
  
  const currentTime = performance.now()
  const elapsed = currentTime - animationStartTime.value
  const progress = Math.min(elapsed / animationDuration, 1) // 0 bis 1
  
  // Easing anwenden für realistische Bewegung
  const easedProgress = easeInOutCubic(progress)
  
  // Aktuelle Position berechnen (interpolieren zwischen Start und Ziel)
  const startPosition = currentAnimatedPosition.value
  const distance = targetPosition.value - startPosition
  currentAnimatedPosition.value = startPosition + (distance * easedProgress)
  
  // Canvas neu zeichnen mit neuer Position
  drawShutter()
  
  // Animation fortsetzen oder beenden
  if (progress < 1) {
    animationId.value = requestAnimationFrame(animationLoop)
  } else {
    // Animation beendet
    currentAnimatedPosition.value = targetPosition.value
    isAnimating.value = false
    drawShutter()
  }
}

/**
 * Animation stoppen
 */
function stopAnimation() {
  if (animationId.value) {
    cancelAnimationFrame(animationId.value)
    animationId.value = null
  }
  isAnimating.value = false
}

/**
 * Canvas initialisieren und Rollladen zeichnen
 */
function drawShutter() {
  const canvas = canvasRef.value
  if (!canvas) return
  
  const ctx = canvas.getContext('2d')
  
  // Canvas Größe setzen (wichtig für scharfe Darstellung)
  canvas.width = canvasWidth.value
  canvas.height = canvasHeight.value
  
  // Canvas leeren
  ctx.clearRect(0, 0, canvasWidth.value, canvasHeight.value)
  
  // Farben definieren (an dein Dark Theme angepasst)
  const colors = {
    window: '#ffffff',           // Weißes Fenster
    frame: '#e0e0e0',           // Heller Rahmen
    rollerBox: '#a0a0a0',       // Rollkasten
    slat: '#c0c0c0',           // Lamellen Hauptfarbe
    slatShadow: '#909090',      // Lamellen Schatten für 3D-Effekt
    slatHighlight: '#e0e0e0'    // Lamellen Highlight
  }
  
  drawWindow(ctx, colors)
  drawRollerBox(ctx, colors)
  drawSlats(ctx, colors)
}

/**
 * Slider Canvas zeichnen
 */
function drawSlider() {
  const canvas = sliderCanvasRef.value
  if (!canvas) return
  
  const ctx = canvas.getContext('2d')
  
  // Canvas Größe setzen (dynamische Höhe!)
  canvas.width = sliderWidth
  canvas.height = sliderHeight.value
  
  // Canvas leeren
  ctx.clearRect(0, 0, sliderWidth, sliderHeight.value)
  
  // Slider-Farben (Glas-Design)
  const sliderColors = {
    track: 'rgba(255, 255, 255, 0.1)',
    trackBorder: 'rgba(255, 255, 255, 0.2)',
    fill: 'rgba(33, 150, 243, 0.6)',
    fillBorder: 'rgba(33, 150, 243, 0.3)',
    thumb: 'rgba(33, 150, 243, 0.9)',
    thumbBorder: 'rgba(255, 255, 255, 0.3)'
  }
  
  drawSliderTrack(ctx, sliderColors)
  drawSliderFill(ctx, sliderColors)
  drawSliderThumb(ctx, sliderColors)
}

/**
 * Slider Track (Hintergrund) zeichnen
 */
function drawSliderTrack(ctx, colors) {
  const trackWidth = 6
  const trackX = (sliderWidth - trackWidth) / 2
  
  // Track Hintergrund mit Glas-Effekt
  ctx.fillStyle = colors.track
  ctx.fillRect(trackX, 10, trackWidth, sliderHeight.value - 20)
  
  // Track Border
  ctx.strokeStyle = colors.trackBorder
  ctx.lineWidth = 1
  ctx.strokeRect(trackX, 10, trackWidth, sliderHeight.value - 20)
}

/**
 * Slider Fill (gefüllter Bereich) zeichnen
 */
function drawSliderFill(ctx, colors) {
  const trackWidth = 6
  const trackX = (sliderWidth - trackWidth) / 2
  const fillHeight = ((100 - sliderPosition.value) / 100) * (sliderHeight.value - 20)
  
  if (fillHeight > 0) {
    // Fill mit Glas-Effekt
    ctx.fillStyle = colors.fill
    ctx.fillRect(trackX, 10, trackWidth, fillHeight)
    
    // Fill Border
    ctx.strokeStyle = colors.fillBorder
    ctx.lineWidth = 1
    ctx.strokeRect(trackX, 10, trackWidth, fillHeight)
  }
}

/**
 * Slider Thumb (Griff) zeichnen
 */
function drawSliderThumb(ctx, colors) {
  const thumbSize = 12
  const thumbX = (sliderWidth - thumbSize) / 2
  const thumbY = 10 + ((100 - sliderPosition.value) / 100) * (sliderHeight.value - 20) - thumbSize / 2
  
  // Thumb mit Glas-Effekt
  ctx.fillStyle = colors.thumb
  ctx.beginPath()
  ctx.roundRect(thumbX, thumbY, thumbSize, thumbSize, 6)
  ctx.fill()
  
  // Thumb Border
  ctx.strokeStyle = colors.thumbBorder
  ctx.lineWidth = 2
  ctx.stroke()
  
  // Thumb Highlight
  ctx.fillStyle = 'rgba(255, 255, 255, 0.3)'
  ctx.beginPath()
  ctx.roundRect(thumbX + 1, thumbY + 1, thumbSize - 2, 3, 2)
  ctx.fill()
}

/**
 * Fenster mit Rahmen zeichnen
 */
function drawWindow(ctx, colors) {
  const frameWidth = 8 // Rahmenbreite in Pixeln
  
  // Äußerer Rahmen (grau)
  ctx.fillStyle = colors.frame
  ctx.fillRect(0, 20, canvasWidth.value, canvasHeight.value - 20) // 20px für Rollkasten
  
  // Inneres Fenster (weiß)
  ctx.fillStyle = colors.window
  ctx.fillRect(
    frameWidth, 
    20 + frameWidth, 
    canvasWidth.value - (frameWidth * 2), 
    canvasHeight.value - 20 - (frameWidth * 2)
  )
  
  // Mittlerer Rahmen (Fensterteiler)
  ctx.fillStyle = colors.frame
  ctx.fillRect(
    canvasWidth.value / 2 - 2, 
    20 + frameWidth, 
    4, 
    canvasHeight.value - 20 - (frameWidth * 2)
  )
}

/**
 * Rollkasten oben zeichnen
 */
function drawRollerBox(ctx, colors) {
  // Rollkasten Hintergrund
  ctx.fillStyle = colors.rollerBox
  ctx.fillRect(0, 0, canvasWidth.value, 20)
  
  // Rollkasten 3D-Effekt (oberer Highlight)
  ctx.fillStyle = colors.slatHighlight
  ctx.fillRect(0, 0, canvasWidth.value, 3)
  
  // Rollkasten 3D-Effekt (unterer Schatten)
  ctx.fillStyle = colors.slatShadow
  ctx.fillRect(0, 17, canvasWidth.value, 3)
}

/**
 * Rollladen-Lamellen zeichnen (mit Animation)
 */
function drawSlats(ctx, colors) {
  const slatHeight = 4 // Höhe einer Lamelle in Pixeln (kleiner für 120px Höhe)
  const slatSpacing = 1 // Abstand zwischen Lamellen
  const totalSlatHeight = slatHeight + slatSpacing
  
  // Berechnen wie viele Lamellen bei aktueller Position sichtbar sind
  const maxSlats = Math.floor((canvasHeight.value - 20) / totalSlatHeight)
  
  // WICHTIG: Verwende animierte Position statt props.position!
  const displayPosition = isAnimating.value ? currentAnimatedPosition.value : props.position
  
  // Position invertieren: 0% = geschlossen (alle Lamellen), 100% = offen (keine Lamellen)
  const currentSlats = Math.floor(((100 - displayPosition) / 100) * maxSlats)
  
  // Startposition für Lamellen (direkt unter dem Rollkasten)
  let currentY = 20
  
  // Jede Lamelle einzeln zeichnen
  for (let i = 0; i < currentSlats; i++) {
    drawSingleSlat(ctx, colors, currentY, slatHeight)
    currentY += totalSlatHeight
  }
}

/**
 * Eine einzelne Lamelle mit 3D-Effekt zeichnen
 */
function drawSingleSlat(ctx, colors, y, height) {
  // Lamellen-Grundfarbe
  ctx.fillStyle = colors.slat
  ctx.fillRect(0, y, canvasWidth.value, height)
  
  // Oberer Highlight für 3D-Effekt
  ctx.fillStyle = colors.slatHighlight
  ctx.fillRect(0, y, canvasWidth.value, 1)
  
  // Unterer Schatten für 3D-Effekt
  ctx.fillStyle = colors.slatShadow
  ctx.fillRect(0, y + height - 1, canvasWidth.value, 1)
  
  // Seitliche Schatten für mehr Tiefe
  ctx.fillStyle = colors.slatShadow
  ctx.fillRect(0, y, 2, height)
  ctx.fillRect(canvasWidth.value - 2, y, 2, height)
}

/**
 * Maus-Position zu Slider-Wert konvertieren
 */
function getSliderValueFromY(y) {
  const canvas = sliderCanvasRef.value
  if (!canvas) return sliderPosition.value
  
  const rect = canvas.getBoundingClientRect()
  const relativeY = y - rect.top
  const clampedY = Math.max(10, Math.min(relativeY, sliderHeight.value - 10))
  
  // Y-Position zu Prozentwert (invertiert, da 0% oben ist)
  return 100 - ((clampedY - 10) / (sliderHeight.value - 20)) * 100
}

/**
 * Drag-Start Handler
 */
function startDragging(event) {
  isDragging.value = true
  handlePositionUpdate(event)
  event.preventDefault()
}

/**
 * Mouse Move Handler
 */
function handleMouseMove(event) {
  if (!isDragging.value) return
  handlePositionUpdate(event)
}

/**
 * Touch Move Handler
 */
function handleTouchMove(event) {
  if (!isDragging.value) return
  const touch = event.touches[0]
  handlePositionUpdate({ clientY: touch.clientY })
  event.preventDefault()
}

/**
 * Position Update Handler (beim Slider-Dragging)
 */
function handlePositionUpdate(event) {
  const newValue = getSliderValueFromY(event.clientY)
  sliderPosition.value = Math.round(newValue)
  
  // Beim Dragging: Keine Animation, direkte Aktualisierung für responsives Feedback
  currentAnimatedPosition.value = sliderPosition.value
  
  // Slider neu zeichnen
  drawSlider()
  
  // Rollladen neu zeichnen (ohne Animation während Drag)
  drawShutter()
  
  // Position-Änderung emittieren
  emit('positionChange', sliderPosition.value)
}

/**
 * Drag-Stop Handler
 */
function stopDragging() {
  if (isDragging.value) {
    // Nach dem Dragging eine sanfte Animation zur finalen Position
    setTimeout(() => {
      animateToPosition(sliderPosition.value)
    }, 50) // Kurze Verzögerung für besseres UX
  }
  isDragging.value = false
}

/**
 * Canvas-Klick Handler
 */
function handleCanvasClick(event) {
  console.log('Rollladen-Canvas geklickt:', event)
}

/**
 * Canvas Größe anpassen
 */
function resizeCanvas() {
  canvasWidth.value = props.width
  // 4:3 Querformat für den Rollladen (Breite zu Höhe = 4:3)
  canvasHeight.value = Math.round(props.width * 0.75) // 3/4 = 0.75
  
  nextTick(() => {
    drawShutter()
    drawSlider()
  })
}

/**
 * Slider Position mit Props synchronisieren
 */
function syncSliderPosition() {
  sliderPosition.value = props.position
  drawSlider()
}

/**
 * Animation beim Cleanup stoppen
 */
function cleanup() {
  stopAnimation()
}

// Beim Mounten Canvas initialisieren
onMounted(() => {
  // Initiale Positionen setzen
  currentAnimatedPosition.value = props.position
  sliderPosition.value = props.position
  
  resizeCanvas()
  syncSliderPosition()
})

// Cleanup beim Unmount
import { onUnmounted } from 'vue'
onUnmounted(() => {
  cleanup()
})

// Props-Änderungen überwachen und animieren
import { watch } from 'vue'
watch(() => props.position, (newPosition, oldPosition) => {
  // Nur animieren wenn sich die Position wirklich geändert hat
  // und wir nicht gerade am Dragging sind
  if (Math.abs(newPosition - oldPosition) > 0.5 && !isDragging.value) {
    // Animation zu neuer Position starten
    animateToPosition(newPosition)
  }
  
  // Slider-Position synchronisieren
  syncSliderPosition()
})

watch(() => props.width, () => {
  resizeCanvas()
})
</script>

<style scoped>
.shutter-container {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.shutter-canvas {
  border: 1px solid #444;
  border-radius: 4px;
  background-color: #f5f5f5;
  cursor: pointer;
  transition: transform 0.2s ease;
}

.shutter-canvas:hover {
  transform: scale(1.02);
}

.slider-canvas {
  border: 1px solid rgba(33, 150, 243, 0.3);
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(10px);
  cursor: pointer;
  transition: all 0.2s ease;
}

.slider-canvas:hover {
  border-color: rgba(33, 150, 243, 0.5);
  background: rgba(33, 150, 243, 0.05);
}
</style>