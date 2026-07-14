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
  title: string
  body: string | null
  payload: Record<string, unknown>
  channel: string
  receivedAt: string
  read: boolean
  source: 'push' | 'sync'
}

export interface InboxApiItem {
  id: number
  channel: string
  status: string
  sent_at: string | null
  created_at: string
  notification: {
    id: number
    title: string
    body: string | null
    payload: Record<string, unknown>
  }
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
