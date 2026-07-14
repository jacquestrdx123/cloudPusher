import { defineStore } from 'pinia'
import { fetchInbox, markAllInboxRead, markInboxRead } from '@/services/api'
import { loadCachedInbox, saveCachedInbox } from '@/services/storage'
import type { AppSettings, InboxApiItem, ReceivedNotification } from '@/types/notification'

function inboxItemToNotification(item: InboxApiItem): ReceivedNotification {
  return {
    id: `inbox-${item.id}`,
    serverId: item.id,
    title: item.title,
    body: item.body,
    payload: item.payload ?? {},
    channel: item.channel,
    deliveredAt: item.delivered_at ?? item.created_at,
    readAt: item.read_at,
    read: item.read,
    source: 'sync',
  }
}

function pushPayloadToNotification(payload: {
  title: string
  body: string | null
  data: Record<string, unknown>
}): ReceivedNotification {
  const pushNotificationId = payload.data.push_notification_id

  return {
    id: pushNotificationId ? `push-${pushNotificationId}` : `push-${Date.now()}`,
    serverId: null,
    title: payload.title,
    body: payload.body,
    payload: payload.data,
    channel: 'push',
    deliveredAt: new Date().toISOString(),
    readAt: null,
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
          new Date(b.deliveredAt).getTime() - new Date(a.deliveredAt).getTime(),
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

    async markRead(id: string, settings?: AppSettings): Promise<void> {
      const item = this.items.find((entry) => entry.id === id)

      if (!item || item.read) {
        return
      }

      item.read = true
      item.readAt = new Date().toISOString()

      if (settings && item.serverId !== null) {
        try {
          const updated = await markInboxRead(settings, item.serverId)
          item.read = updated.read
          item.readAt = updated.read_at
        } catch {
          // Keep optimistic read state locally.
        }
      }

      await this.persistCache()
    },

    async markAllRead(settings?: AppSettings): Promise<void> {
      if (settings) {
        try {
          await markAllInboxRead(settings)
        } catch {
          // Fall through to local update.
        }
      }

      this.items.forEach((item) => {
        item.read = true
        item.readAt = item.readAt ?? new Date().toISOString()
      })

      await this.persistCache()
    },

    async syncInbox(settings: AppSettings): Promise<void> {
      this.syncing = true
      this.error = null

      try {
        const response = await fetchInbox(settings)
        const synced = response.data.map(inboxItemToNotification)

        const merged = new Map<string, ReceivedNotification>()

        for (const item of synced) {
          merged.set(item.serverId ? `inbox-${item.serverId}` : item.id, item)
        }

        for (const item of this.items) {
          if (item.source === 'push' && item.serverId === null) {
            merged.set(item.id, item)
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
