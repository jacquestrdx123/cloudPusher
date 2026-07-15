<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>cloudPusher</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <div class="hero">
        <h1>Sign in</h1>
        <p>Enter your mobile number and password to continue.</p>
      </div>

      <ion-list>
        <ion-item>
          <ion-input
            v-model="form.phone"
            label="Mobile number"
            label-placement="stacked"
            type="tel"
            placeholder="+27821234567"
            autocomplete="tel"
          />
        </ion-item>
        <ion-item>
          <ion-input
            v-model="form.password"
            label="Password"
            label-placement="stacked"
            type="password"
            autocomplete="current-password"
          />
        </ion-item>
      </ion-list>

      <div class="actions">
        <ion-button expand="block" :disabled="busy || !canSubmit" @click="signIn">
          Sign in
        </ion-button>
        <ion-button expand="block" fill="clear" router-link="/register">
          Need an account? Register
        </ion-button>
      </div>

      <ion-toast
        :is-open="Boolean(message)"
        :message="message ?? ''"
        :color="messageColor"
        duration="3500"
        @didDismiss="message = null"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonButton,
  IonContent,
  IonHeader,
  IonInput,
  IonItem,
  IonList,
  IonPage,
  IonTitle,
  IonToast,
  IonToolbar,
} from '@ionic/vue'
import { useSettings } from '@/composables/useSettings'
import { loginWithPassword } from '@/services/api'

const router = useRouter()
const { hydrate, update, configured } = useSettings()

const busy = ref(false)
const message = ref<string | null>(null)
const messageColor = ref<'success' | 'danger' | 'warning'>('success')

const form = reactive({
  phone: '',
  password: '',
})

const canSubmit = computed(
  () => form.phone.trim().length >= 8 && form.password.length > 0,
)

onMounted(async () => {
  const current = await hydrate()

  if (configured.value) {
    router.replace('/tabs/inbox')
    return
  }

  form.phone = current.userPhone
})

async function signIn(): Promise<void> {
  busy.value = true
  message.value = null

  try {
    const phone = form.phone.trim()
    const response = await loginWithPassword(phone, form.password)

    if (!response.user.company?.slug) {
      throw new Error('This account is not linked to a company.')
    }

    await update({
      accessToken: response.token,
      companySlug: response.user.company.slug,
      companyName: response.user.company.name,
      userId: response.user.id,
      userName: response.user.name,
      userEmail: response.user.email,
      userPhone: response.user.phone ?? phone,
      isCompanyAdmin: Boolean(response.user.is_company_admin),
    })

    router.replace('/tabs/inbox')
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Sign in failed'
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.hero {
  margin: 1rem 0 1.5rem;
}

.hero h1 {
  margin: 0 0 0.5rem;
}

.hero p {
  margin: 0;
  color: var(--ion-color-medium);
  line-height: 1.5;
}

.actions {
  display: grid;
  gap: 0.5rem;
  margin-top: 1.5rem;
}
</style>
