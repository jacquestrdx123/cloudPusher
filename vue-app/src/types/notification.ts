export interface AppSettings {
  apiBaseUrl: string
  companySlug: string
  apiToken: string
  userEmail: string
  soundEnabled: boolean
  deviceName: string
}

export interface ReceivedNotification {
  id: string
  serverId: number | null
  title: string
  body: string | null
  payload: Record<string, unknown>
  channel: string
  deliveredAt: string
  readAt: string | null
  read: boolean
  source: 'push' | 'sync'
}

export interface InboxApiItem {
  id: number
  title: string
  body: string | null
  payload: Record<string, unknown>
  channel: string
  delivered_at: string | null
  read_at: string | null
  read: boolean
  created_at: string
  push_notification_id: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
