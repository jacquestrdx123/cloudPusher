<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Approvals</ion-title>
        <ion-buttons slot="end">
          <ion-button @click="refresh" :disabled="loading">
            <ion-icon slot="icon-only" :icon="refreshOutline" />
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <div v-if="!isCompanyAdmin" class="empty-state">
        <h2>Company admin only</h2>
        <p>Ask a platform admin to grant you company admin access.</p>
      </div>

      <div v-else-if="loading" class="empty-state">
        <ion-spinner name="crescent" />
        <p>Loading registrations…</p>
      </div>

      <div v-else-if="items.length === 0" class="empty-state">
        <h2>No pending registrations</h2>
        <p>New requests for {{ settings?.companyName || settings?.companySlug }} will show up here.</p>
      </div>

      <ion-list v-else>
        <ion-item v-for="item in items" :key="item.id">
          <ion-label>
            <h2>{{ item.name }}</h2>
            <p>{{ item.phone }}</p>
            <p>{{ item.email }}</p>
            <p>Requested {{ formatDate(item.created_at) }}</p>
          </ion-label>
          <div slot="end" class="item-actions">
            <ion-button
              size="small"
              color="success"
              :disabled="busyId === item.id"
              @click="approve(item.id)"
            >
              Approve
            </ion-button>
            <ion-button
              size="small"
              color="danger"
              fill="outline"
              :disabled="busyId === item.id"
              @click="reject(item.id)"
            >
              Reject
            </ion-button>
          </div>
        </ion-item>
      </ion-list>

      <ion-toast
        :is-open="Boolean(message)"
        :message="message ?? ''"
        :color="messageColor"
        duration="3000"
        @didDismiss="message = null"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonSpinner,
  IonTitle,
  IonToast,
  IonToolbar,
} from '@ionic/vue'
import { refreshOutline } from 'ionicons/icons'
import { useSettings } from '@/composables/useSettings'
import {
  approveRegistration,
  fetchRegistrations,
  rejectRegistration,
} from '@/services/api'
import type { UserRegistrationItem } from '@/types/notification'

const { settings, hydrate } = useSettings()
const items = ref<UserRegistrationItem[]>([])
const loading = ref(true)
const busyId = ref<number | null>(null)
const message = ref<string | null>(null)
const messageColor = ref<'success' | 'danger'>('success')

const isCompanyAdmin = computed(() => Boolean(settings.value?.isCompanyAdmin))

function formatDate(value: string): string {
  return new Date(value).toLocaleString()
}

async function refresh(): Promise<void> {
  const current = settings.value ?? (await hydrate())

  if (!current.isCompanyAdmin || !current.accessToken) {
    loading.value = false
    return
  }

  loading.value = true

  try {
    const response = await fetchRegistrations(current, 'pending')
    items.value = response.data
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Failed to load registrations'
  } finally {
    loading.value = false
  }
}

async function approve(id: number): Promise<void> {
  if (!settings.value) {
    return
  }

  busyId.value = id

  try {
    await approveRegistration(settings.value, id)
    items.value = items.value.filter((item) => item.id !== id)
    messageColor.value = 'success'
    message.value = 'Registration approved'
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Approve failed'
  } finally {
    busyId.value = null
  }
}

async function reject(id: number): Promise<void> {
  if (!settings.value) {
    return
  }

  busyId.value = id

  try {
    await rejectRegistration(settings.value, id, 'Rejected by company admin')
    items.value = items.value.filter((item) => item.id !== id)
    messageColor.value = 'success'
    message.value = 'Registration rejected'
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Reject failed'
  } finally {
    busyId.value = null
  }
}

onMounted(async () => {
  await hydrate()
  await refresh()
})
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  min-height: 50vh;
  padding: 2rem;
  text-align: center;
  color: var(--ion-color-medium);
}

.item-actions {
  display: grid;
  gap: 0.35rem;
}
</style>
