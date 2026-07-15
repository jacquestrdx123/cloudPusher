import firebaseJson from '../../firebase.json'

/** Production / Capacitor API host (hardcoded for now). */
export const API_ORIGIN = 'https://cloudpusher-backend.on-forge.com'

export const config = {
  /**
   * In `npm run dev`, use the Vite proxy (same-origin `/api`) to avoid CORS.
   * Everywhere else call the API origin directly.
   */
  apiBaseUrl: import.meta.env.DEV ? '' : API_ORIGIN,
  firebase: {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY || firebaseJson.apiKey || '',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || firebaseJson.authDomain || '',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || firebaseJson.projectId || '',
    storageBucket:
      import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || firebaseJson.storageBucket || '',
    messagingSenderId:
      import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || firebaseJson.messagingSenderId || '',
    appId: import.meta.env.VITE_FIREBASE_APP_ID || firebaseJson.appId || '',
    measurementId:
      import.meta.env.VITE_FIREBASE_MEASUREMENT_ID || firebaseJson.measurementId || '',
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
