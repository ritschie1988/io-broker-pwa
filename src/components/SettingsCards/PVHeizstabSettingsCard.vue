<template>
  <v-card flat>
    <v-card-title>PV-Heizstab Steuerung</v-card-title>
    <v-card-text>
      <v-text-field
        v-model="pvThreshold"
        label="PV-Überschuss Schwellenwert (Watt)"
        type="number"
      />
      <v-text-field
        v-model="tempMin"
        label="Minimale Warmwasser-Temperatur (°C)"
        type="number"
      />
      <v-text-field
        v-model="tempMax"
        label="Maximale Warmwasser-Temperatur (°C)"
        type="number"
      />
      <v-switch v-model="override" label="Override aktivieren (Heizstab erzwingen/sperren)" />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue'
const props = defineProps({
  pvThreshold: Number,
  tempMin: Number,
  tempMax: Number,
  override: Boolean
})
const emit = defineEmits(['update:pvThreshold', 'update:tempMin', 'update:tempMax', 'update:override'])

const pvThreshold = ref(props.pvThreshold)
const tempMin = ref(props.tempMin)
const tempMax = ref(props.tempMax)
const override = ref(props.override)

watch(pvThreshold, val => emit('update:pvThreshold', val))
watch(tempMin, val => emit('update:tempMin', val))
watch(tempMax, val => emit('update:tempMax', val))
watch(override, val => emit('update:override', val))
</script>
