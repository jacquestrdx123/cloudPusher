<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Settings</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <ion-list>
        <ion-list-header>cloudPusher connection</ion-list-header>

        <ion-item>
          <ion-input
            v-model="form.apiBaseUrl"
            label="API base URL"
            label-placement="stacked"
            placeholder="https://push.example.com"
          />
        </ion-item>

        <ion-item>
          <ion-input
            v-model="form.companySlug"
            label="Company slug"
            label-placement="stacked"
            placeholder="acme-corp"
          />
        </ion-item>

        <ion-item>
          <ion-input
            v-model="form.apiToken"
            label="API token"
            label-placement="stacked"
            type="password"
          />
        </ion-item>

        <ion-item>
          <ion-input
            v-model="form.userEmail"
            label="Your email"
            label-placement="stacked"
            type="email"
            placeholder="you@company.com"
          />
        </ion-item>

        <ion-item>
          <ion-input
            v-model="form.deviceName"
            label="Device name"
            label-placement="stacked"
            placeholder="iPhone 15, Pixel 8, Browser"
          />
        </ion-item>

        <ion-item>
          <ion-toggle v-model="form.soundEnabled">Play sound on receive</ion-toggle>
        </ion-item>
      </ion-list>

      <div class="actions">
        <ion-button expand="block" @click="save" :disabled="saving">
          Save & connect
        </ion-button>
        <ion-button expand="block" fill="outline" @click="testSound">
          Test sound
        </ion-button>
        <ion-button expand="block" fill="clear" @click="requestPermission">
          Request push permission
        </ion-button>
      </div>

      <ion-note class="help">
        Native iOS/Android builds use Capacitor push notifications. PWA/web builds
        require Firebase config in <code>.env</code> (see <code>.env.example</code>).
      </ion-note>

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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonButton,
  IonContent,
  IonHeader,
  IonInput,
  IonItem,
  IonList,
  IonListHeader,
  IonNote,
  IonPage,
  IonTitle,
  IonToast,
  IonToggle,
  IonToolbar,
} from '@ionic/vue'
import { useSettings } from '@/composables/useSettings'
import { testConnection } from '@/services/api'
import { requestNotificationPermission } from '@/services/push'
import { playNotificationSound, unlockAudio } from '@/services/sound'
import type { AppSettings } from '@/types/notification'

const router = useRouter()
const { hydrate, update } = useSettings()

const form = reactive<AppSettings>({
  apiBaseUrl: 'http://localhost:8000',
  companySlug: '',
  apiToken: '',
  userEmail: '',
  soundEnabled: true,
  deviceName: '',
})

const saving = ref(false)
const message = ref<string | null>(null)
const messageColor = ref<'success' | 'danger' | 'warning'>('success')

onMounted(async () => {
  const current = await hydrate()
  Object.assign(form, current)
})

async function save(): Promise<void> {
  saving.value = true
  message.value = null

  try {
    const saved = await update({ ...form })
    await testConnection(saved)
    messageColor.value = 'success'
    message.value = 'Connected successfully'
    router.replace('/tabs/inbox')
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Connection failed'
  } finally {
    saving.value = false
  }
}

async function testSound(): Promise<void> {
  await unlockAudio()
  await playNotificationSound(true)
  messageColor.value = 'success'
  message.value = 'Sound played'
}

async function requestPermission(): Promise<void> {
  const granted = await requestNotificationPermission()
  messageColor.value = granted ? 'success' : 'warning'
  message.value = granted ? 'Permission granted' : 'Permission denied'
}
</script>

<style scoped>
.actions {
  display: grid;
  gap: 0.5rem;
  margin-top: 1.5rem;
}

.help {
  display: block;
  margin-top: 1.5rem;
  line-height: 1.5;
}
</style>
