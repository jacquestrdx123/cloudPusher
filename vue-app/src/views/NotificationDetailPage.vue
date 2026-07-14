<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-buttons slot="start">
          <ion-back-button default-href="/tabs/inbox" />
        </ion-buttons>
        <ion-title>Notification</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding" v-if="notification">
      <ion-card>
        <ion-card-header>
          <ion-card-subtitle>{{ notification.channel.toUpperCase() }}</ion-card-subtitle>
          <ion-card-title>{{ notification.title }}</ion-card-title>
        </ion-card-header>
        <ion-card-content>
          <p v-if="notification.body">{{ notification.body }}</p>
          <ion-note>{{ formattedTime }}</ion-note>
        </ion-card-content>
      </ion-card>

      <ion-list v-if="payloadEntries.length">
        <ion-list-header>Payload</ion-list-header>
        <ion-item v-for="[key, value] in payloadEntries" :key="key">
          <ion-label>
            <h3>{{ key }}</h3>
            <p>{{ formatValue(value) }}</p>
          </ion-label>
        </ion-item>
      </ion-list>
    </ion-content>

    <ion-content v-else class="ion-padding">
      <p>Notification not found.</p>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  IonBackButton,
  IonButtons,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonListHeader,
  IonNote,
  IonPage,
  IonTitle,
  IonToolbar,
} from '@ionic/vue'
import { useNotificationStore } from '@/stores/notifications'

const route = useRoute()
const store = useNotificationStore()

const notification = computed(() =>
  store.items.find((item) => item.id === decodeURIComponent(String(route.params.id))),
)

const payloadEntries = computed(() =>
  Object.entries(notification.value?.payload ?? {}),
)

const formattedTime = computed(() => {
  if (!notification.value) {
    return ''
  }

  return new Date(notification.value.receivedAt).toLocaleString()
})

function formatValue(value: unknown): string {
  return typeof value === 'object' ? JSON.stringify(value) : String(value)
}
</script>
