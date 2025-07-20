<template>
  <v-alert
    v-if="visible && notificationsEnabled && messages.length > 0"
    type="error"
    class="notification-bar"
    border="start"
    variant="outlined"
    prominent
  >
    <div v-for="msg in messages" :key="msg.id">{{ msg.text }}</div>
  </v-alert>
</template>

<script setup>
import { computed, toRefs } from 'vue'
const props = defineProps({
  messages: { type: Array, default: () => [] },
  notificationsEnabled: { type: Boolean, default: true },
  visible: { type: Boolean, default: true }
})
</script>

<style scoped>
.notification-bar {
  width: 100vw;
  max-width: 100%;
  margin-bottom: 1rem;
  border-radius: 8px;
  border-left: 4px solid #e53935;
  background: rgba(255, 235, 238, 0.95);
  color: #b71c1c;
  font-weight: 500;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
</style>
