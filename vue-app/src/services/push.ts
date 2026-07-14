import { Capacitor } from '@capacitor/core'
import { Device } from '@capacitor/device'
import {
  LocalNotifications,
  type LocalNotificationSchema,
} from '@capacitor/local-notifications'
import {
  PushNotifications,
  type PushNotificationSchema,
  type Token,
} from '@capacitor/push-notifications'
import { initializeApp, type FirebaseApp } from 'firebase/app'
import {
  getMessaging,
  getToken,
  isSupported,
  onMessage,
  type Messaging,
} from 'firebase/messaging'
import { config, isFirebaseConfigured } from '@/config/env'
import { registerDeviceToken } from '@/services/api'
import { playNotificationSound } from '@/services/sound'
import type { AppSettings } from '@/types/notification'

export type PushPayload = {
  title: string
  body: string | null
  data: Record<string, unknown>
}

type PushHandler = (payload: PushPayload) => void

let firebaseApp: FirebaseApp | null = null
let messaging: Messaging | null = null
let initialized = false
let currentToken: string | null = null

function parsePushData(
  data: Record<string, string> | undefined,
): Record<string, unknown> {
  if (!data) {
    return {}
  }

  const parsed: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(data)) {
    try {
      parsed[key] = JSON.parse(value)
    } catch {
      parsed[key] = value
    }
  }

  return parsed
}

function payloadFromNative(notification: PushNotificationSchema): PushPayload {
  const data = parsePushData(notification.data as Record<string, string> | undefined)

  return {
    title: notification.title ?? (data.title as string) ?? 'Notification',
    body: notification.body ?? (data.body as string) ?? null,
    data,
  }
}

async function ensureLocalNotificationPermissions(): Promise<void> {
  if (!Capacitor.isNativePlatform()) {
    return
  }

  const status = await LocalNotifications.checkPermissions()

  if (status.display !== 'granted') {
    await LocalNotifications.requestPermissions()
  }
}

async function showForegroundNotification(payload: PushPayload): Promise<void> {
  if (Capacitor.isNativePlatform()) {
    await ensureLocalNotificationPermissions()

    const notification: LocalNotificationSchema = {
      id: Math.floor(Math.random() * 1_000_000),
      title: payload.title,
      body: payload.body ?? '',
      sound: 'default',
      extra: payload.data,
    }

    await LocalNotifications.schedule({ notifications: [notification] })

    return
  }

  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification(payload.title, {
      body: payload.body ?? undefined,
      icon: '/favicon.png',
      tag: String(payload.data.push_notification_id ?? Date.now()),
    })
  }
}

async function registerNativePush(
  settings: AppSettings,
  onReceived: PushHandler,
): Promise<void> {
  let permStatus = await PushNotifications.checkPermissions()

  if (permStatus.receive === 'prompt') {
    permStatus = await PushNotifications.requestPermissions()
  }

  if (permStatus.receive !== 'granted') {
    throw new Error('Push notification permission was denied.')
  }

  await PushNotifications.addListener('registration', async (token: Token) => {
    currentToken = token.value
    const platform = Capacitor.getPlatform() === 'ios' ? 'apns' : 'fcm'
    const device = await Device.getInfo()

    await registerDeviceToken(settings, {
      platform,
      token: token.value,
      name: settings.deviceName || device.name || device.model,
    })
  })

  await PushNotifications.addListener('registrationError', (error) => {
    console.error('Push registration failed', error)
  })

  await PushNotifications.addListener(
    'pushNotificationReceived',
    async (notification: PushNotificationSchema) => {
      const payload = payloadFromNative(notification)

      await playNotificationSound(settings.soundEnabled)
      await showForegroundNotification(payload)
      onReceived(payload)
    },
  )

  await PushNotifications.addListener(
    'pushNotificationActionPerformed',
    async (action) => {
      const payload = payloadFromNative(action.notification)

      onReceived(payload)
    },
  )

  await PushNotifications.register()
}

async function registerWebPush(
  settings: AppSettings,
  onReceived: PushHandler,
): Promise<void> {
  if (!isFirebaseConfigured()) {
    console.warn('Firebase is not configured; web push is disabled.')

    return
  }

  const supported = await isSupported()

  if (!supported) {
    throw new Error('This browser does not support Firebase messaging.')
  }

  if (!firebaseApp) {
    firebaseApp = initializeApp(config.firebase)
    messaging = getMessaging(firebaseApp)
  }

  if (!messaging) {
    throw new Error('Firebase messaging could not be initialized.')
  }

  const permission = await Notification.requestPermission()

  if (permission !== 'granted') {
    throw new Error('Notification permission was denied.')
  }

  const token = await getToken(messaging, {
    vapidKey: config.firebase.vapidKey,
    serviceWorkerRegistration: await navigator.serviceWorker.register(
      '/firebase-messaging-sw.js',
    ),
  })

  currentToken = token

  await registerDeviceToken(settings, {
    platform: 'fcm',
    token,
    name: settings.deviceName || 'Web browser',
  })

  onMessage(messaging, async (message) => {
    const payload: PushPayload = {
      title: message.notification?.title ?? 'Notification',
      body: message.notification?.body ?? null,
      data: parsePushData(message.data as Record<string, string> | undefined),
    }

    await playNotificationSound(settings.soundEnabled)
    await showForegroundNotification(payload)
    onReceived(payload)
  })
}

export async function initializePushNotifications(
  settings: AppSettings,
  onReceived: PushHandler,
): Promise<void> {
  if (initialized) {
    return
  }

  initialized = true

  if (Capacitor.isNativePlatform()) {
    await registerNativePush(settings, onReceived)

    return
  }

  await registerWebPush(settings, onReceived)
}

export function getCurrentPushToken(): string | null {
  return currentToken
}

export async function requestNotificationPermission(): Promise<boolean> {
  if (Capacitor.isNativePlatform()) {
    const status = await PushNotifications.requestPermissions()

    return status.receive === 'granted'
  }

  if (!('Notification' in window)) {
    return false
  }

  return (await Notification.requestPermission()) === 'granted'
}
