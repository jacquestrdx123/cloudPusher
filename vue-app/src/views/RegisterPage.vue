<template>
  <ion-page>
    <ion-header>
      <ion-toolbar color="primary">
        <ion-buttons slot="start">
          <ion-back-button default-href="/login" />
        </ion-buttons>
        <ion-title>Register</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <div class="hero">
        <h1>Request access</h1>
        <p>
          Enter your company slug, details, and password. A company admin must
          approve your registration before you can sign in.
        </p>
      </div>

      <ion-list>
        <ion-item>
          <ion-input
            v-model="form.companySlug"
            label="Company slug"
            label-placement="stacked"
            placeholder="acme-corp"
            autocomplete="organization"
          />
        </ion-item>
        <ion-item>
          <ion-input
            v-model="form.name"
            label="Full name"
            label-placement="stacked"
            autocomplete="name"
          />
        </ion-item>
        <ion-item>
          <ion-input
            v-model="form.email"
            label="Email"
            label-placement="stacked"
            type="email"
            autocomplete="email"
          />
        </ion-item>
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
            autocomplete="new-password"
          />
        </ion-item>
        <ion-item>
          <ion-input
            v-model="form.passwordConfirmation"
            label="Confirm password"
            label-placement="stacked"
            type="password"
            autocomplete="new-password"
          />
        </ion-item>
      </ion-list>

      <div class="actions">
        <ion-button expand="block" :disabled="busy || !canSubmit" @click="submit">
          Submit registration
        </ion-button>
        <ion-button expand="block" fill="clear" router-link="/login">
          Back to sign in
        </ion-button>
      </div>

      <ion-toast
        :is-open="Boolean(message)"
        :message="message ?? ''"
        :color="messageColor"
        duration="4000"
        @didDismiss="message = null"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonBackButton,
  IonButton,
  IonButtons,
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
import { registerCompanyUser } from '@/services/api'

const router = useRouter()
const busy = ref(false)
const message = ref<string | null>(null)
const messageColor = ref<'success' | 'danger'>('success')

const form = reactive({
  companySlug: '',
  name: '',
  email: '',
  phone: '',
  password: '',
  passwordConfirmation: '',
})

const canSubmit = computed(
  () =>
    Boolean(
      form.companySlug.trim() &&
        form.name.trim() &&
        form.email.trim() &&
        form.phone.trim().length >= 8 &&
        form.password.length >= 8 &&
        form.password === form.passwordConfirmation,
    ),
)

async function submit(): Promise<void> {
  busy.value = true
  message.value = null

  try {
    await registerCompanyUser(form.companySlug.trim(), {
      name: form.name.trim(),
      email: form.email.trim(),
      phone: form.phone.trim(),
      password: form.password,
      password_confirmation: form.passwordConfirmation,
    })

    messageColor.value = 'success'
    message.value = 'Registration submitted. You can sign in after a company admin approves you.'
    setTimeout(() => router.replace('/login'), 1500)
  } catch (error) {
    messageColor.value = 'danger'
    message.value = error instanceof Error ? error.message : 'Registration failed'
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
