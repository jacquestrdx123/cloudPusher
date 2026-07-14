<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Inbox</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="markAllRead" :disabled="store.unreadCount === 0">
            <ion-icon slot="icon-only" :icon="checkmarkDoneOutline" />
          </ion-button>
          <ion-button @click="refresh" :disabled="store.syncing">
            <ion-icon slot="icon-only" :icon="refreshOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      <ion-toolbar v-if="store.pushReady" color="light">
        <ion-title size="small">
          <ion-icon :icon="radioOutline" color="success" /> Push active
          <span v-if="store.unreadCount"> · {{ store.unreadCount }} unread</span>
        </ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <ion-refresher slot="fixed" @ionRefresh="handleRefresh">
        <ion-refresher-content />
      </ion-refresher>

      <div v-if="!configured" class="empty-state">
        <ion-icon :icon="settingsOutline" />
        <h2>Connect to cloudPusher</h2>
        <p>Configure your company API credentials to receive notifications.</p>
        <ion-button router-link="/tabs/settings">Open settings</ion-button>
      </div>

      <div v-else-if="store.loading" class="empty-state">
        <ion-spinner name="crescent" />
        <p>Loading notifications…</p>
      </div>

      <div v-else-if="store.sortedItems.length === 0" class="empty-state">
        <ion-icon :icon="notificationsOffOutline" />
        <h2>No notifications yet</h2>
        <p>When a push arrives, it will appear here with sound.</p>
      </div>

      <ion-list v-else>
        <NotificationItem
          v-for="item in store.sortedItems"
          :key="item.id"
          :notification="item"
          @select="openNotification"
        />
      </ion-list>

      <ion-toast
        :is-open="Boolean(toastMessage)"
        :message="toastMessage ?? ''"
        duration="2500"
        @didDismiss="toastMessage = null"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonList,
  IonPage,
  IonRefresher,
  IonRefresherContent,
  IonSpinner,
  IonTitle,
  IonToast,
  IonToolbar,
  RefresherCustomEvent,
} from '@ionic/vue'
import {
  checkmarkDoneOutline,
  notificationsOffOutline,
  radioOutline,
  refreshOutline,
  settingsOutline,
} from 'ionicons/icons'
import NotificationItem from '@/components/NotificationItem.vue'
import { useSettings } from '@/composables/useSettings'
import { initializePushNotifications } from '@/services/push'
import { playNotificationSound, unlockAudio } from '@/services/sound'
import { useNotificationStore } from '@/stores/notifications'
import type { ReceivedNotification } from '@/types/notification'

const router = useRouter()
const store = useNotificationStore()
const { settings, configured, hydrate } = useSettings()
const toastMessage = ref<string | null>(null)

async function bootstrap(): Promise<void> {
  await store.hydrateFromCache()
  const current = await hydrate()

  if (!configured.value) {
    return
  }

  store.loading = true

  try {
    await store.syncInbox(current)

    await initializePushNotifications(current, async (payload) => {
      store.addFromPush(payload)
      await playNotificationSound(current.soundEnabled)
      toastMessage.value = payload.title
    })

    store.pushReady = true
  } catch (error) {
    toastMessage.value =
      error instanceof Error ? error.message : 'Failed to connect'
  } finally {
    store.loading = false
  }
}

async function refresh(): Promise<void> {
  if (!settings.value) {
    return
  }

  await store.syncInbox(settings.value)
}

async function handleRefresh(event: RefresherCustomEvent): Promise<void> {
  await refresh()
  event.target.complete()
}

async function markAllRead(): Promise<void> {
  await store.markAllRead(settings.value ?? undefined)
}

async function openNotification(notification: ReceivedNotification): Promise<void> {
  await store.markRead(notification.id, settings.value ?? undefined)
  router.push(`/tabs/inbox/${encodeURIComponent(notification.id)}`)
}

onMounted(async () => {
  await unlockAudio()
  await bootstrap()
})
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  min-height: 60vh;
  padding: 2rem;
  text-align: center;
}

.empty-state ion-icon {
  font-size: 3rem;
  color: var(--ion-color-medium);
}

.empty-state h2 {
  margin: 0;
}

.empty-state p {
  margin: 0;
  color: var(--ion-color-medium);
}
</style>
