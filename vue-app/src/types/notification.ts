export interface AppSettings {
  companySlug: string
  companyName: string
  accessToken: string
  userId: number | null
  userName: string
  userEmail: string
  userPhone: string
  isCompanyAdmin: boolean
  soundEnabled: boolean
  deviceName: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  phone: string | null
  locale: string | null
  company_id: number | null
  is_company_admin: boolean
  company: {
    id: number
    name: string
    slug: string
  } | null
}

export interface UserRegistrationItem {
  id: number
  name: string
  email: string
  phone: string
  status: 'pending' | 'approved' | 'rejected'
  review_notes: string | null
  reviewed_at: string | null
  created_at: string
  user_id: number | null
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
