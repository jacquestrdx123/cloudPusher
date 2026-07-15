import { Preferences } from '@capacitor/preferences'
import type { AppSettings } from '@/types/notification'

const SETTINGS_KEY = 'cloudpusher_settings'
const INBOX_KEY = 'cloudpusher_inbox'

const defaults: AppSettings = {
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
}

export async function loadSettings(): Promise<AppSettings> {
  const { value } = await Preferences.get({ key: SETTINGS_KEY })

  if (!value) {
    return { ...defaults }
  }

  const parsed = JSON.parse(value) as Partial<AppSettings>

  return {
    ...defaults,
    ...parsed,
    accessToken: parsed.accessToken ?? '',
  }
}

export async function saveSettings(settings: AppSettings): Promise<void> {
  await Preferences.set({
    key: SETTINGS_KEY,
    value: JSON.stringify(settings),
  })
}

export async function clearSettings(): Promise<void> {
  await Preferences.set({
    key: SETTINGS_KEY,
    value: JSON.stringify({ ...defaults }),
  })
}

export async function loadCachedInbox(): Promise<string | null> {
  const { value } = await Preferences.get({ key: INBOX_KEY })

  return value
}

export async function saveCachedInbox(serialized: string): Promise<void> {
  await Preferences.set({ key: INBOX_KEY, value: serialized })
}

export async function clearCachedInbox(): Promise<void> {
  await Preferences.remove({ key: INBOX_KEY })
}

export function isConfigured(settings: AppSettings): boolean {
  return Boolean(settings.companySlug && settings.accessToken)
}
