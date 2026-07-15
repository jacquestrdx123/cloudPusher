/** Production / Capacitor API host (hardcoded for now). */
export const API_ORIGIN = 'https://push-service.test'

export const config = {
  /**
   * In `npm run dev`, use the Vite proxy (same-origin `/api`) so self-signed
   * Herd TLS certs are ignored. Everywhere else call the API origin directly.
   */
  apiBaseUrl: import.meta.env.DEV ? '' : API_ORIGIN,
  firebase: {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY ?? '',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN ?? '',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID ?? '',
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID ?? '',
    appId: import.meta.env.VITE_FIREBASE_APP_ID ?? '',
    vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY ?? '',
  },
  soundPath: '/sounds/notification.mp3',
  appName: 'cloudPusher',
}

export function isFirebaseConfigured(): boolean {
  return Boolean(
    config.firebase.apiKey &&
      config.firebase.projectId &&
      config.firebase.messagingSenderId &&
      config.firebase.appId &&
      config.firebase.vapidKey,
  )
}
