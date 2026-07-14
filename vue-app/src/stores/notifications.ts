import { defineStore } from 'pinia'
import { fetchInbox } from '@/services/api'
import { loadCachedInbox, saveCachedInbox } from '@/services/storage'
import type { AppSettings, InboxApiItem, ReceivedNotification } from '@/types/notification'

function inboxItemToNotification(item: InboxApiItem): ReceivedNotification {
  return {
    id: `sync-${item.id}`,
    title: item.notification.title,
    body: item.notification.body,
    payload: item.notification.payload ?? {},
    channel: item.channel,
    receivedAt: item.sent_at ?? item.created_at,
    read: true,
    source: 'sync',
  }
}

function pushPayloadToNotification(payload: {
  title: string
  body: string | null
  data: Record<string, unknown>
}): ReceivedNotification {
  const id = String(
    payload.data.push_notification_id ??
      payload.data.id ??
      `push-${Date.now()}`,
  )

  return {
    id: `push-${id}`,
    title: payload.title,
    body: payload.body,
    payload: payload.data,
    channel: 'push',
    receivedAt: new Date().toISOString(),
    read: false,
    source: 'push',
  }
}

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    items: [] as ReceivedNotification[],
    loading: false,
    syncing: false,
    error: null as string | null,
    pushReady: false,
    lastSyncAt: null as string | null,
  }),

  getters: {
    unreadCount: (state) => state.items.filter((item) => !item.read).length,
    sortedItems: (state) =>
      [...state.items].sort(
        (a, b) =>
          new Date(b.receivedAt).getTime() - new Date(a.receivedAt).getTime(),
      ),
  },

  actions: {
    async hydrateFromCache(): Promise<void> {
      const cached = await loadCachedInbox()

      if (!cached) {
        return
      }

      this.items = JSON.parse(cached) as ReceivedNotification[]
    },

    async persistCache(): Promise<void> {
      await saveCachedInbox(JSON.stringify(this.items.slice(0, 200)))
    },

    addFromPush(payload: {
      title: string
      body: string | null
      data: Record<string, unknown>
    }): ReceivedNotification {
      const notification = pushPayloadToNotification(payload)
      const existingIndex = this.items.findIndex((item) => item.id === notification.id)

      if (existingIndex >= 0) {
        this.items[existingIndex] = notification
      } else {
        this.items.unshift(notification)
      }

      void this.persistCache()

      return notification
    },

    markRead(id: string): void {
      const item = this.items.find((entry) => entry.id === id)

      if (item) {
        item.read = true
        void this.persistCache()
      }
    },

    markAllRead(): void {
      this.items.forEach((item) => {
        item.read = true
      })
      void this.persistCache()
    },

    async syncInbox(settings: AppSettings): Promise<void> {
      this.syncing = true
      this.error = null

      try {
        const response = await fetchInbox(settings)
        const synced = response.data.map(inboxItemToNotification)

        const merged = new Map<string, ReceivedNotification>()

        for (const item of [...synced, ...this.items]) {
          const key = `${item.title}-${item.receivedAt}`
          const existing = merged.get(key)

          if (!existing || (!item.read && existing.read)) {
            merged.set(key, item)
          }
        }

        this.items = [...merged.values()]
        this.lastSyncAt = new Date().toISOString()
        await this.persistCache()
      } catch (error) {
        this.error = error instanceof Error ? error.message : 'Sync failed'
        throw error
      } finally {
        this.syncing = false
      }
    },
  },
})
