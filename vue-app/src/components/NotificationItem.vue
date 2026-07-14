<template>
  <ion-item
    button
    :detail="true"
    lines="full"
    :class="{ unread: !notification.read }"
    @click="$emit('select', notification)"
  >
    <ion-icon
      slot="start"
      :icon="notificationIcon"
      :color="notification.read ? 'medium' : 'primary'"
      aria-hidden="true"
    />
    <ion-label>
      <h2>{{ notification.title }}</h2>
      <p v-if="notification.body">{{ notification.body }}</p>
      <ion-note>{{ formattedTime }}</ion-note>
    </ion-label>
    <ion-badge v-if="!notification.read" slot="end" color="primary">New</ion-badge>
  </ion-item>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  IonBadge,
  IonIcon,
  IonItem,
  IonLabel,
  IonNote,
} from '@ionic/vue'
import {
  mailOutline,
  notificationsOutline,
  phonePortraitOutline,
} from 'ionicons/icons'
import type { ReceivedNotification } from '@/types/notification'

const props = defineProps<{
  notification: ReceivedNotification
}>()

defineEmits<{
  select: [notification: ReceivedNotification]
}>()

const notificationIcon = computed(() => {
  switch (props.notification.channel) {
    case 'mail':
      return mailOutline
    case 'sms':
      return phonePortraitOutline
    default:
      return notificationsOutline
  }
})

const formattedTime = computed(() => {
  const date = new Date(props.notification.deliveredAt)

  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
})
</script>

<style scoped>
.unread h2 {
  font-weight: 700;
}
</style>
