<template>
  <v-card flat>
    <v-card-title>Verbindungseinstellungen</v-card-title>
    <v-card-text>
      <v-text-field
        v-model="apiUrl"
        label="API-URL für ioBroker Proxy"
        type="text"
      />
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue'
const props = defineProps({
  apiUrl: String
})
const emit = defineEmits(['update:apiUrl'])
const apiUrl = ref(props.apiUrl)
watch(apiUrl, val => emit('update:apiUrl', val))
</script>
