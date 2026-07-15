<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Settings</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <ion-list>
        <ion-list-header>Account</ion-list-header>

        <ion-item>
          <ion-label>
            <h2>{{ form.userName || 'Signed in' }}</h2>
            <p>{{ form.userPhone }}</p>
            <p v-if="form.userEmail">{{ form.userEmail }}</p>
            <p>{{ form.companyName || form.companySlug }}</p>
            <p v-if="form.isCompanyAdmin">Company admin</p>
          </ion-label>
        </ion-item>

        <ion-list-header>Preferences</ion-list-header>

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
          Save preferences
        </ion-button>
        <ion-button expand="block" fill="outline" @click="testSound">
          Test sound
        </ion-button>
        <ion-button expand="block" fill="outline" @click="requestPermission">
          Request push permission
        </ion-button>
        <ion-button expand="block" fill="clear" color="danger" @click="signOut" :disabled="saving">
          Sign out
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
  IonLabel,
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
import { logout as apiLogout } from '@/services/api'
import { requestNotificationPermission } from '@/services/push'
import { playNotificationSound, unlockAudio } from '@/services/sound'
import type { AppSettings } from '@/types/notification'

const router = useRouter()
const { hydrate, update, reset } = useSettings()

const form = reactive<AppSettings>({
  companySlug: '',
  companyName: '',
  accessToken: '',
  userId: null,
  userName: '',
  userEmail: '',
  userPhone: '',
  isCompanyAdmin: false,
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
    await update({
      deviceName: form.deviceName,
      soundEnabled: form.soundEnabled,
    })
    messageColor.value = 'success'
    message.value = 'Preferences saved'
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Save failed'
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

async function signOut(): Promise<void> {
  saving.value = true
  message.value = null

  try {
    const current = await hydrate()

    if (current.accessToken) {
      try {
        await apiLogout(current)
      } catch {
        // Still clear local session if the API revoke fails.
      }
    }

    await reset()
    router.replace('/login')
  } finally {
    saving.value = false
  }
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
