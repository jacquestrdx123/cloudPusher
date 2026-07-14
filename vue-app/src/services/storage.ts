import { Preferences } from '@capacitor/preferences'
import type { AppSettings } from '@/types/notification'

const SETTINGS_KEY = 'cloudpusher_settings'
const INBOX_KEY = 'cloudpusher_inbox'

const defaults: AppSettings = {
  apiBaseUrl: 'http://localhost:8000',
  companySlug: '',
  apiToken: '',
  userEmail: '',
  soundEnabled: true,
  deviceName: '',
}

export async function loadSettings(): Promise<AppSettings> {
  const { value } = await Preferences.get({ key: SETTINGS_KEY })

  if (!value) {
    return { ...defaults }
  }

  return { ...defaults, ...JSON.parse(value) }
}

export async function saveSettings(settings: AppSettings): Promise<void> {
  await Preferences.set({
    key: SETTINGS_KEY,
    value: JSON.stringify(settings),
  })
}

export async function loadCachedInbox(): Promise<string | null> {
  const { value } = await Preferences.get({ key: INBOX_KEY })

  return value
}

export async function saveCachedInbox(serialized: string): Promise<void> {
  await Preferences.set({ key: INBOX_KEY, value: serialized })
}

export function isConfigured(settings: AppSettings): boolean {
  return Boolean(
    settings.apiBaseUrl &&
      settings.companySlug &&
      settings.apiToken &&
      settings.userEmail,
  )
}
