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

Run Horizon (required for delivery — needs Redis with `QUEUE_CONNECTION=redis`):

```bash
php artisan horizon
```

Dashboard: `/horizon` (local open; production requires a global admin). Admin panel: `/admin`.

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

### Directory sync (users/groups)

A Global Admin creates the company in Filament (which stores a per-company
`hmac_secret`). An upstream system then keeps that company's users and user
groups in sync using the company secret:

```http
PUT /api/v1/{company_slug}/sync
Authorization: Bearer {company_hmac_secret}

{
  "users": [
    { "external_id": "u-1", "name": "Jane Doe", "mobile_number": "+27821234567", "email": "jane@acme.test", "is_company_admin": true },
    { "external_id": "u-2", "name": "John Roe", "mobile_number": "+27829876543" }
  ],
  "groups": [
    {
      "external_id": "g-1",
      "name": "Engineering",
      "members": [ { "external_id": "u-1" }, { "mobile_number": "+27829876543" } ]
    }
  ],
  "delete_missing_users": false,
  "delete_missing_groups": false
}
```

- Users are matched (within the company) by `external_id`, then `mobile_number`,
  then `email`; unmatched records create a new platform user. Users are processed
  before groups, so newly synced users can be referenced as group members in
  the same request.
- A group's `members` list becomes its authoritative membership. Members must
  already belong to the company; unresolved references are reported under
  `groups.skipped`.
- `delete_missing_users` detaches company members absent from the payload (the
  global user is preserved); `delete_missing_groups` deletes absent groups.
- Both `users` and `groups` are optional — send either or both.

The response summarises the reconciliation:

```json
{
  "company": { "id": 1, "name": "Acme Corp", "slug": "acme-corp" },
  "users":  { "created": 2, "updated": 0, "unchanged": 0, "removed": 0, "skipped": [] },
  "groups": { "created": 1, "updated": 0, "unchanged": 0, "removed": 0, "members_synced": 2, "skipped": [] }
}
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

The Ionic Vue receiver is a separate repo: [`cloudPusherApp`](https://github.com/jacquestrdx123/cloudPusherApp) (local folder `../cloudPusher-app`). Open [`cloudPusher.code-workspace`](../cloudPusher.code-workspace) to work on both projects together.


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
