import { ref, computed } from 'vue'
import {
  clearCachedInbox,
  clearSettings,
  loadSettings,
  saveSettings,
  isConfigured,
} from '@/services/storage'
import type { AppSettings } from '@/types/notification'

const settings = ref<AppSettings | null>(null)
const loading = ref(true)

export function useSettings() {
  const configured = computed(() =>
    settings.value ? isConfigured(settings.value) : false,
  )

  async function hydrate(): Promise<AppSettings> {
    loading.value = true

    try {
      settings.value = await loadSettings()

      return settings.value
    } finally {
      loading.value = false
    }
  }

  async function update(partial: Partial<AppSettings>): Promise<AppSettings> {
    const current = settings.value ?? (await hydrate())
    const next = { ...current, ...partial }

    await saveSettings(next)
    settings.value = next

    return next
  }

  async function reset(): Promise<AppSettings> {
    await clearSettings()
    await clearCachedInbox()
    settings.value = await loadSettings()

    return settings.value
  }

  return {
    settings,
    loading,
    configured,
    hydrate,
    update,
    reset,
  }
}
