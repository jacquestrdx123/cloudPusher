<template>
  <ion-page>
    <ion-tabs>
      <ion-router-outlet />
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="inbox" href="/tabs/inbox">
          <ion-icon :icon="mailOutline" />
          <ion-label>Inbox</ion-label>
          <ion-badge v-if="store.unreadCount" color="danger">{{ store.unreadCount }}</ion-badge>
        </ion-tab-button>
        <ion-tab-button tab="settings" href="/tabs/settings">
          <ion-icon :icon="settingsOutline" />
          <ion-label>Settings</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonBadge,
  IonIcon,
  IonLabel,
  IonPage,
  IonRouterOutlet,
  IonTabBar,
  IonTabButton,
  IonTabs,
} from '@ionic/vue'
import { mailOutline, settingsOutline } from 'ionicons/icons'
import { useNotificationStore } from '@/stores/notifications'

const store = useNotificationStore()
</script>
