# cloudPusher

A multi-tenant push notification platform for general-purpose outbound messaging. Accept notification requests from your apps via REST API or signed webhooks, then deliver across push (FCM/APNs), email, and SMS — with delivery tracking and an admin panel.

## Features

- **Multi-tenant** — Each company has its own users, groups, device tokens, and notification history
- **Multi-channel** — Push (FCM + APNs), email, and SMS (Vonage)
- **Flexible targeting** — Single user, user group, or broadcast to all company users
- **Scheduled delivery** — Queue notifications for future delivery
- **Delivery tracking** — Per-user, per-channel success/failure audit log
- **Robust lifecycle** — Pending → processing → sent / partial / failed based on actual delivery outcomes
- **Invalid token cleanup** — Automatically removes permanently invalid FCM/APNs tokens
- **Rate limiting** — Per-company API and webhook throttling
- **Admin panel** — Filament UI to manage tenants, send notifications, and inspect delivery logs

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed   # optional
```

Run the queue worker (required for delivery):

```bash
php artisan queue:work
```

Access the admin panel at `/admin`.

### Admin roles

| Role | Seeded login | Access |
|------|--------------|--------|
| **Global admin** | `admin@example.com` / `password` | Every company, Companies nav, create tenants |
| **Company admin** | `company@example.com` / `password` (Acme) | Own company only: users, groups, registrations, notifications |

```bash
php artisan migrate:fresh --seed
```

## API

All API routes are scoped by company slug. Authenticate with:

```
Authorization: Bearer {company_hmac_secret}
```

### Send a notification

```http
POST /api/v1/{company_slug}/notifications
Content-Type: application/json

{
  "target": { "type": "user", "id": 1 },
  "title": "Hello",
  "body": "World",
  "channels": ["push", "mail"],
  "data": { "action": "open_inbox" },
  "scheduled_at": "2026-07-15T09:00:00Z"
}
```

**Target types:**

| Type | Fields | Description |
|------|--------|-------------|
| `user` | `id` or `email` | Single recipient |
| `group` | `id` or `slug` | All members of a user group |
| `broadcast` | — | Every user in the company |

Returns `202 Accepted` with the notification record.

### List notifications

```http
GET /api/v1/{company_slug}/notifications?per_page=25
```

### Show notification + deliveries

```http
GET /api/v1/{company_slug}/notifications/{id}
```

### Mobile registration + password login

```http
POST /api/v1/{company_slug}/auth/register
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+27821234567",
  "password": "secret-pass",
  "password_confirmation": "secret-pass"
}

POST /api/v1/auth/login
{ "phone": "+27821234567", "password": "secret-pass" }
```

Registration creates a **pending** request (password stored hashed). A company admin must approve it before login works. Login returns a personal `Bearer` token plus `user.company.slug`.

### User inbox (stored notifications)

Each successfully delivered notification is stored per user with `delivered_at` and `read_at`. Prefer a personal user token (no `user[...]` query needed); company HMAC tokens still accept `user[email]` / `user[phone]` / `user[id]`:

```http
GET /api/v1/{company_slug}/inbox
Authorization: Bearer {user_api_token}

GET /api/v1/{company_slug}/inbox?user[email]=you@company.com
Authorization: Bearer {company_hmac_secret}
```

### Register a device token

```http
POST /api/v1/{company_slug}/device-tokens
Authorization: Bearer {user_api_token}

{
  "platform": "fcm",
  "token": "...",
  "name": "Pixel 8"
}
```


### Webhook (HMAC-signed)

```http
POST /api/webhooks/{company_slug}/push
X-Signature: sha256={hmac_sha256_of_raw_body}
```

Sign the raw request body with the company's `hmac_secret` using HMAC-SHA256.

## Configuration

Key environment variables (see `.env.example`):

| Variable | Description |
|----------|-------------|
| `PUSH_FCM_ENABLED` | Enable Firebase Cloud Messaging |
| `PUSH_APNS_ENABLED` | Enable Apple Push Notifications |
| `PUSH_SMS_ENABLED` | Enable Vonage SMS |
| `PUSH_MAIL_ENABLED` | Enable email fallback |
| `PUSH_QUEUE` | Queue name for notification jobs |
| `PUSH_RATE_LIMIT` | API requests per minute per company |
| `PUSH_FINALIZE_DELAY_SECONDS` | Wait before aggregating delivery status |
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins for the Ionic receiver app |

## Mobile receiver app

The Ionic Vue receiver lives in `vue-app/`. See [vue-app/README.md](vue-app/README.md) for setup, native builds (iOS/Android), and PWA configuration.


```
Webhook / API → DispatchPushNotification → ProcessPushNotification (job)
                                                    ↓
                                          WebhookPushNotification (per user)
                                                    ↓
                                    FCM / APNs / Mail / Vonage
                                                    ↓
                              RecordNotificationDelivery (listener)
                                                    ↓
                              FinalizePushNotificationStatus (job)
```

## Testing

```bash
php artisan test --compact
```

See also `postman/Push Service API.postman_collection.json` for example requests.

## License

MIT
