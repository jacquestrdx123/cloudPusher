import type { AppSettings, InboxApiItem, PaginatedResponse } from '@/types/notification'

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

function headers(settings: AppSettings): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${settings.apiToken}`,
  }
}

function apiUrl(settings: AppSettings, path: string): string {
  const base = settings.apiBaseUrl.replace(/\/$/, '')

  return `${base}/api/v1/${settings.companySlug}${path}`
}

function userQuery(settings: AppSettings): string {
  return new URLSearchParams({
    'user[email]': settings.userEmail,
  }).toString()
}

async function request<T>(
  settings: AppSettings,
  path: string,
  init?: RequestInit,
): Promise<T> {
  const response = await fetch(apiUrl(settings, path), {
    ...init,
    headers: {
      ...headers(settings),
      ...(init?.headers ?? {}),
    },
  })

  if (!response.ok) {
    let message = `Request failed (${response.status})`

    try {
      const body = await response.json()
      message = body.message ?? message
    } catch {
      // ignore parse errors
    }

    throw new ApiError(message, response.status)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

export async function registerDeviceToken(
  settings: AppSettings,
  payload: {
    platform: 'fcm' | 'apns'
    token: string
    name?: string
  },
): Promise<void> {
  await request(settings, '/device-tokens', {
    method: 'POST',
    body: JSON.stringify({
      user: { email: settings.userEmail },
      platform: payload.platform,
      token: payload.token,
      name: payload.name,
    }),
  })
}

export async function unregisterDeviceToken(
  settings: AppSettings,
  deviceTokenId: number,
): Promise<void> {
  await request(settings, `/device-tokens/${deviceTokenId}`, {
    method: 'DELETE',
  })
}

export async function fetchInbox(
  settings: AppSettings,
  page = 1,
): Promise<PaginatedResponse<InboxApiItem>> {
  const params = new URLSearchParams({
    'user[email]': settings.userEmail,
    page: String(page),
    per_page: '50',
  })

  return request<PaginatedResponse<InboxApiItem>>(settings, `/inbox?${params}`)
}

export async function markInboxRead(
  settings: AppSettings,
  inboxId: number,
): Promise<InboxApiItem> {
  const response = await request<{ data: InboxApiItem }>(
    settings,
    `/inbox/${inboxId}/read?${userQuery(settings)}`,
    { method: 'PATCH' },
  )

  return response.data
}

export async function markAllInboxRead(settings: AppSettings): Promise<void> {
  await request(settings, `/inbox/read-all?${userQuery(settings)}`, {
    method: 'PATCH',
  })
}

export async function testConnection(settings: AppSettings): Promise<boolean> {
  const params = new URLSearchParams({
    'user[email]': settings.userEmail,
    per_page: '1',
  })

  await request(settings, `/inbox?${params}`)

  return true
}
