# cloudPusher Integration Guide

**Audience:** engineers implementing user directory sync and outbound push notifications from another system into cloudPusher (`push-service`).

**Base URL (local):** `https://push-service.test`  
**API prefix:** `/api`

This document covers the **system-to-system** path only:

1. Obtain a company (tenant) created by a cloudPusher Global Admin
2. Sync users and groups from your system
3. Send push / mail / SMS notifications to those users

It does **not** cover the mobile app login / self-registration flows (those use personal user tokens). For machine integration you must use the company's `hmac_secret` — never end-user tokens.

---

## Table of contents

1. [Architecture overview](#1-architecture-overview)
2. [Authentication](#2-authentication)
3. [Prerequisites](#3-prerequisites)
4. [Step 1 — Obtain a company](#4-step-1--obtain-a-company)
5. [Step 2 — Sync users and groups](#5-step-2--sync-users-and-groups)
6. [Step 3 — Send notifications](#6-step-3--send-notifications)
7. [Optional — HMAC webhook send path](#7-optional--hmac-webhook-send-path)
8. [Inspect notification status](#8-inspect-notification-status)
9. [Errors, rate limits, and retries](#9-errors-rate-limits-and-retries)
10. [Recommended integration design](#10-recommended-integration-design)
11. [End-to-end walkthrough](#11-end-to-end-walkthrough)
12. [Reference — request / response schemas](#12-reference--request--response-schemas)
13. [Implementation checklist](#13-implementation-checklist)
14. [What cloudPusher does *not* do for you](#14-what-cloudpusher-does-not-do-for-you)

---

## 1. Architecture overview

```
┌─────────────────────┐         ┌──────────────────────────┐
│  Your system        │         │  cloudPusher             │
│  (ERP / CRM / LMS)  │         │  (push-service)          │
│                     │         │                          │
│  (company created   │         │  Global Admin → Company  │
│   out of band)      │         │  + hmac_secret in DB     │
│  1. Sync directory  │──PUT───▶│  Users + Groups          │
│  2. Send notify     │──POST──▶│  Queue → FCM/APNs/Mail   │
└─────────────────────┘         └───────────┬──────────────┘
                                            │
                                            ▼
                                 Mobile / Web receiver app
                                 (device tokens registered
                                  by the end user)
```

**Important separation of concerns**

| Concern | Who owns it |
|---------|-------------|
| Creating the company tenant | cloudPusher Global Admin (Filament) |
| Keeping users/groups in sync | Your system → cloudPusher sync API |
| Registering FCM/APNs device tokens | End-user app (cloudPusherApp), not your backend |
| Deciding *when* to notify | Your system |
| Delivering the push | cloudPusher (Horizon workers + FCM/APNs) |

Syncing a user into cloudPusher does **not** by itself make push delivery work. The user must also open the receiver app and grant notification permission so a device token is stored. Until then, mail/SMS channels (if enabled) are the only delivery paths for that user.

---

## 2. Authentication

cloudPusher uses **Bearer tokens**. For system-to-system work there is one secret per company — not a logged-in user token and not a shared platform env key.

### 2.1 Company HMAC secret

| Item | Value |
|------|--------|
| Issued when | A Global Admin creates the company in Filament (auto-generated, stored on `companies.hmac_secret`) |
| Header | `Authorization: Bearer {hmac_secret}` |
| Used for | Sync, send notifications, list/show notifications, device-token ops (company style), inbox (company style) |
| Also used for | Signing webhook body (`X-Signature`) |

Copy the **Company API token / HMAC secret** and **slug** from Filament into your secrets store. Use that secret for every company-scoped API call.

### 2.2 What not to use

| Token type | Use for system integration? |
|------------|----------------------------|
| User API token (from mobile login) | **No** |
| Filament admin session cookie | **No** |
| Shared platform / env provisioning key | **No** (removed) |
| Random / guessed company secret | **No** |

---

## 3. Prerequisites

On the cloudPusher side (ops):

1. A Global Admin creates the company tenant in Filament and shares `slug` + `hmac_secret` with the integrating team.
2. Horizon running (`php artisan horizon`) with Redis — without this, notifications stay queued / undelivered.
3. Channel drivers configured as needed: FCM (`PUSH_FCM_ENABLED`), APNs, mail, SMS.

On your system:

1. Secure storage for each company's `slug` and `hmac_secret`.
2. A stable **external ID** for every user and group you sync (recommended — makes rematches and email changes safe).
3. An HTTPS client that can send JSON and read status codes.

---

## 4. Step 1 — Obtain a company

Companies are **not** created via the public API. A cloudPusher **Global Admin** creates the tenant in Filament (`/admin/companies/create` or tenant registration).

On create, cloudPusher:

- Generates a unique `slug` from the name (or uses the slug the admin entered)
- Auto-generates a 48-character `hmac_secret` (shown as “Company API token / HMAC secret”)

**Persist `slug` and `hmac_secret` in your system immediately.** You need them for every subsequent company-scoped call. The admin can regenerate the secret later from the company form if it is rotated.

There is no `POST /api/v1/companies` endpoint.

---

## 5. Step 2 — Sync users and groups

Declarative, idempotent reconciliation of the company directory.

### Request

```http
PUT /api/v1/{company_slug}/sync
Authorization: Bearer {hmac_secret}
Content-Type: application/json
Accept: application/json
```

You may send `users`, `groups`, or both. Omit a key entirely to leave that side untouched.

### Auth for sync

Only the company's `hmac_secret` is accepted (scoped to that company).

### User records

```json
{
  "users": [
    {
      "external_id": "u-1001",
      "name": "Jane Doe",
      "mobile_number": "+27821234567",
      "email": "jane@acme.test",
      "locale": "en",
      "is_company_admin": true
    },
    {
      "external_id": "u-1002",
      "name": "John Roe",
      "mobile_number": "+27829876543"
    }
  ],
  "delete_missing_users": false
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `mobile_number` | **Yes** | Primary identity (normalized E.164-ish). Legacy `phone` is accepted as an alias |
| `external_id` | Strongly recommended | Your system's immutable user id; stored on the company↔user pivot |
| `email` | No | Optional; used as a secondary match key. A placeholder is stored if omitted |
| `name` | No | Defaults to mobile number on create |
| `locale` | No | Optional locale string |
| `is_company_admin` | No | Company-admin flag on the pivot |

#### How users are matched (order matters)

Within the company:

1. Pivot `external_id` (if provided)
2. Global `mobile_number` / `users.phone`
3. Global `email` (if provided)

Unmatched → create a new platform user (random password; not meant for mobile login unless you also run the registration flow).

#### Profile update rules

- If the user was created by this company (or is already a member), profile fields can update on re-sync.
- If the user already exists globally under another company, sync **attaches** them to your company but does **not** overwrite their global name/email/mobile owned by the other tenant.

#### `delete_missing_users`

- `false` (default / omit): only upsert; never remove memberships.
- `true`: members of this company who are **absent from this payload** are **detached** from the company (group memberships cleared). The global `users` row is kept.

Use `delete_missing_users: true` only when the payload is a **full** directory snapshot. Partial / incremental syncs must leave it `false`.

### Group records

```json
{
  "groups": [
    {
      "external_id": "g-eng",
      "name": "Engineering",
      "slug": "engineering",
      "members": [
        { "external_id": "u-1001" },
        { "mobile_number": "+27829876543" }
      ]
    }
  ],
  "delete_missing_groups": false
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `name` or `slug` | Required to **create** | At least one needed for new groups |
| `external_id` | Strongly recommended | Your system's group id |
| `slug` | No | Auto from name if omitted |
| `members` | No | If present, becomes the **authoritative** membership list |

Member refs resolve by `external_id`, then `mobile_number`, then `email`. Members must already belong to the company — sync users in the **same request** (users run first) or in a prior sync. Unresolved members appear under `groups.skipped`.

#### `delete_missing_groups`

Same semantics as users: only pass `true` with a full group snapshot.

### Sync response

```json
{
  "company": { "id": 1, "name": "Acme Corp", "slug": "acme-corp" },
  "users": {
    "created": 2,
    "updated": 0,
    "unchanged": 0,
    "removed": 0,
    "skipped": []
  },
  "groups": {
    "created": 1,
    "updated": 0,
    "unchanged": 0,
    "removed": 0,
    "members_synced": 2,
    "skipped": []
  }
}
```

Always inspect `skipped` arrays. A 200 does not mean every record applied cleanly.

### curl example

```bash
curl -sS -X PUT "$BASE_URL/api/v1/acme-corp/sync" \
  -H "Authorization: Bearer $COMPANY_HMAC_SECRET" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "users": [
      {
        "external_id": "u-1001",
        "name": "Jane Doe",
        "mobile_number": "+27821234567",
        "email": "jane@acme.test",
        "is_company_admin": true
      }
    ],
    "groups": [
      {
        "external_id": "g-ops",
        "name": "Ops",
        "members": [{ "external_id": "u-1001" }]
      }
    ]
  }'
```

### Sync frequency guidance

| Pattern | Recommendation |
|---------|----------------|
| Nightly full dump | OK; set `delete_missing_*` carefully |
| Event-driven upsert (user created/updated) | Preferred; send only changed users; leave `delete_missing_users` false |
| Group membership change | Send that group with full `members` list |
| Hard delete in your system | Either omit them with `delete_missing_users: true` on a full sync, or stop targeting them |

---

## 6. Step 3 — Send notifications

Queue a notification for async delivery. Returns **202 Accepted** immediately; Horizon workers perform delivery.

### Request

```http
POST /api/v1/{company_slug}/notifications
Authorization: Bearer {hmac_secret}
Content-Type: application/json
Accept: application/json
```

**Note:** This endpoint requires the **company HMAC secret**.

### Minimal example — single user by email

```json
{
  "target": {
    "type": "user",
    "email": "jane@acme.test"
  },
  "title": "Invoice ready",
  "body": "Your January invoice is available.",
  "channels": ["push", "mail"]
}
```

### Targets

| `target.type` | Identifier fields | Audience |
|---------------|-------------------|----------|
| `user` | `id` **or** `email` | One company member |
| `group` | `id` **or** `slug` | All members of that group |
| `broadcast` | (none) | Every user in the company |

Examples:

```json
{ "target": { "type": "user", "id": 42 } }
```

```json
{ "target": { "type": "group", "slug": "engineering" } }
```

```json
{ "target": { "type": "broadcast" } }
```

Prefer targeting by **email** (stable across your sync) or by cloudPusher numeric `id` if you stored it. There is no `external_id` on the notification target today — resolve external IDs in your system to email/id before calling, or target a group you synced by slug/`external_id`→slug.

### Full payload fields

| Field | Required | Notes |
|-------|----------|--------|
| `target` | Yes | See above |
| `title` | Yes | Max 255 |
| `body` | No | Max 2000 |
| `channels` | No | Subset of `push`, `mail`, `sms`. Falls back to company `default_channels` |
| `data` | No | Arbitrary JSON object; delivered to clients as notification `payload`. Reserved keys: `url` (`https://…`), `url_label` (button text, max 100) |
| `image_url` | No | Must be `https://…` — rich push image |
| `sound` | No | e.g. `default` |
| `category` | No | iOS category id |
| `android_channel_id` | No | Android notification channel |
| `scheduled_at` | No | ISO-8601 datetime **in the future**; delays dispatch |

### Rich / deep-link example

```json
{
  "target": {
    "type": "user",
    "email": "jane@acme.test"
  },
  "title": "New message",
  "body": "You have a reply from support.",
  "image_url": "https://cdn.example.com/alerts/support.png",
  "sound": "default",
  "category": "RICH_MESSAGE",
  "android_channel_id": "rich_messages_v1",
  "data": {
    "url": "https://wispmon-e0iitbod.on-forge.com/device/1123",
    "url_label": "View device",
    "type": "power_down",
    "entity": "ticket",
    "entity_id": "123"
  },
  "channels": ["push"]
}
```

The receiver app opens the notification in-app first. When `data.url` is an `https://` URL, the detail screen shows a button labeled `data.url_label` (fallback **Open link**) that opens the external URL.
### Success response — 202

```json
{
  "data": {
    "id": 99,
    "status": "pending",
    "target_type": "user",
    "user_id": 1,
    "user_group_id": null,
    "title": "Invoice ready",
    "body": "Your January invoice is available.",
    "image_url": null,
    "sound": null,
    "category": null,
    "android_channel_id": null,
    "payload": {},
    "channels": ["push", "mail"],
    "recipients_count": 0,
    "scheduled_at": null,
    "created_at": "2026-07-22T05:10:00.000000Z",
    "updated_at": "2026-07-22T05:10:00.000000Z"
  }
}
```

Store `data.id` if you need to poll delivery status later.

### Notification status lifecycle

| Status | Meaning |
|--------|---------|
| `pending` | Accepted, waiting for worker |
| `scheduled` | Future `scheduled_at` |
| `processing` | Worker expanding recipients / sending |
| `sent` | All deliveries succeeded |
| `partial` | Mix of success and failure |
| `failed` | All deliveries failed / nothing deliverable |

### curl example

```bash
curl -sS -X POST "$BASE_URL/api/v1/acme-corp/notifications" \
  -H "Authorization: Bearer $COMPANY_HMAC_SECRET" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "target": { "type": "user", "email": "jane@acme.test" },
    "title": "Server down",
    "body": "Investigate production",
    "data": { "severity": "critical", "url": "https://example.com/incidents/1", "url_label": "View incident" },
    "channels": ["push", "mail"]
  }'
```

---

## 7. Optional — HMAC webhook send path

Same notification payload as the v1 API, but authenticated by signing the **raw body** instead of Bearer auth. Prefer the Bearer v1 API for new integrations; keep the webhook if your platform already speaks signed webhooks.

```http
POST /api/webhooks/{company_slug}/push
Content-Type: application/json
X-Signature: sha256={hex_hmac_sha256}
```

### Signature algorithm

1. Take the exact raw HTTP body bytes you will send (do not re-serialize after signing).
2. Compute `HMAC-SHA256(body, company_hmac_secret)`.
3. Hex-encode the digest.
4. Set header: `X-Signature: sha256=<hex>`.

### Pseudo-code (PHP)

```php
$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $body, $companyHmacSecret);

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Signature: sha256='.$signature,
];
```

### Pseudo-code (Node.js)

```js
import crypto from 'node:crypto';

const body = JSON.stringify(payload);
const hex = crypto.createHmac('sha256', companyHmacSecret).update(body).digest('hex');

await fetch(`${baseUrl}/api/webhooks/${slug}/push`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Signature': `sha256=${hex}`,
  },
  body,
});
```

---

## 8. Inspect notification status

### List recent

```http
GET /api/v1/{company_slug}/notifications?per_page=25
Authorization: Bearer {hmac_secret}
```

`per_page` max 100.

### Show one + deliveries

```http
GET /api/v1/{company_slug}/notifications/{id}
Authorization: Bearer {hmac_secret}
```

Includes per-recipient, per-channel delivery rows when loaded (`sent` / `failed`, provider messages, timestamps).

Use this for support tooling or to reconcile “did Jane get the push?” in your own admin UI.

---

## 9. Errors, rate limits, and retries

### Common HTTP statuses

| Status | Typical cause | Retry? |
|--------|---------------|--------|
| 200 / 201 / 202 | Success | — |
| 401 | Bad Bearer / bad signature | No (fix secret) |
| 404 | Unknown slug or inactive company | No |
| 422 | Validation / unknown target | No (fix payload) |
| 429 | Rate limited | Yes, with backoff |
| 5xx | Server / worker infra | Yes, with backoff |

### Rate limits

Configured on cloudPusher via `PUSH_RATE_LIMIT` (default **120 requests/minute** per company for the API limiter). Webhooks have a separate limit (`PUSH_WEBHOOK_RATE_LIMIT`).

### Idempotency advice

| Operation | Idempotent? | How to use safely |
|-----------|-------------|-------------------|
| Sync directory | Yes for same payload | Safe to retry; counts reflect delta |
| Send notification | **No** | Retrying creates a **second** notification. Deduplicate in your system (store outbound event id → cloudPusher notification id) before re-POSTing |

---

## 10. Recommended integration design

### 10.1 Credentials store

```
companies:
  your_tenant_id → {
    cloudpusher_slug,
    cloudpusher_hmac_secret,   # from Filament Global Admin
    last_synced_at
  }
```

### 10.2 Mapping table

Keep a mirror so you can target without guessing:

```
your_user_id  →  email, cloudpusher_user_id (optional), external_id used in sync
your_group_id →  group slug / external_id
```

On sync response you only get aggregate counts (not per-user ids). If you need cloudPusher numeric user ids for targeting, either:

- always target by **email**, or
- look them up via admin UI / a future API, or
- after first successful notify, persist `user_id` from the notification show response.

### 10.3 Suggested job flow in your system

```
on UserCreated / UserUpdated:
  enqueue SyncUserJob(external_id)
    → PUT /sync with single-user array (delete_missing_users=false)

on GroupMembershipChanged:
  enqueue SyncGroupJob(group_external_id)
    → PUT /sync with that group + full members list

on BusinessEventWorthyOfPush:
  enqueue NotifyJob(event)
    → ensure user synced recently
    → POST /notifications
    → store notification id against event for support
```

### 10.4 Channels policy

Decide in your product which channels each event uses:

| Event class | Suggested channels |
|-------------|-------------------|
| Urgent ops alert | `["push", "mail", "sms"]` |
| In-app activity | `["push"]` |
| Marketing / digest | `["mail"]` (or omit push) |

If you omit `channels`, company defaults apply.

### 10.5 PHP client sketch

```php
final class CloudPusherClient
{
    public function __construct(
        private string $baseUrl,
    ) {}

    public function sync(string $slug, array $payload, string $hmacSecret): array
    {
        return $this->request('PUT', "/api/v1/{$slug}/sync", $payload, $hmacSecret);
    }

    public function notify(string $slug, array $payload, string $hmacSecret): array
    {
        return $this->request('POST', "/api/v1/{$slug}/notifications", $payload, $hmacSecret);
    }

    private function request(string $method, string $path, array $json, string $bearer): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withToken($bearer)
            ->acceptJson()
            ->send($method, $path, ['json' => $json]);

        $response->throw();

        return $response->json();
    }
}
```

---

## 11. End-to-end walkthrough

Assume:

- `BASE_URL=https://push-service.test`
- Company `acme-corp` already created by a Global Admin
- `$HMAC` = that company's Filament “Company API token / HMAC secret”

### A. Confirm credentials (once)

Obtain `slug` and `hmac_secret` from the Global Admin (Filament Companies resource). There is no API to create the company.

### B. Sync the directory

```bash
PUT /api/v1/acme-corp/sync
Authorization: Bearer $HMAC

{
  "users": [
    { "external_id": "42", "name": "Jane", "email": "jane@acme.test", "is_company_admin": true }
  ],
  "groups": [
    {
      "external_id": "billing",
      "name": "Billing",
      "slug": "billing",
      "members": [{ "external_id": "42" }]
    }
  ]
}
```

Expect `users.created >= 1`, `groups.created >= 1`, empty `skipped`.

### C. User installs receiver app

Jane opens cloudPusherApp, logs in / is approved for `acme-corp`, grants notifications. App registers FCM/APNs token against her user. **Your integrating system does not call this.**

### D. Fire a notification from your system

```bash
POST /api/v1/acme-corp/notifications
Authorization: Bearer $HMAC

{
  "target": { "type": "group", "slug": "billing" },
  "title": "Payment received",
  "body": "Invoice #889 was paid.",
  "data": { "invoice_id": "889", "url": "https://example.com/invoices/889", "url_label": "View invoice" },
  "channels": ["push", "mail"]
}
```

Expect **202** and a notification `id`.

### E. Confirm delivery

```bash
GET /api/v1/acme-corp/notifications/99
Authorization: Bearer $HMAC
```

Check `status` and `deliveries`.

---

## 12. Reference — request / response schemas

### Provision company

**POST** `/api/v1/companies`

```
Request:
  name: string, required, max 255
  slug: string, optional, alpha_dash, max 255
  default_channels: string[], optional, each in [push, mail, sms]
  is_active: boolean, optional

Response 200|201:
  data: { id, name, slug, hmac_secret, default_channels, is_active, created_at }
  created: boolean
```

### Sync directory

**PUT** `/api/v1/{slug}/sync`

```
Request:
  users?: [
    {
      external_id?: string,
      name?: string,
      mobile_number: string,  # required per row (primary identity)
      email?: string,
      locale?: string,
      is_company_admin?: bool
    }
  ]
  groups?: [
    {
      external_id?: string,
      name?: string,
      slug?: string,
      members?: [
        { external_id?: string, mobile_number?: string, email?: string }
      ]
    }
  ]
  delete_missing_users?: bool   # default false
  delete_missing_groups?: bool  # default false

Response 200:
  company: { id, name, slug }
  users?:  { created, updated, unchanged, removed, skipped[] }
  groups?: { created, updated, unchanged, removed, members_synced, skipped[] }
```

### Send notification

**POST** `/api/v1/{slug}/notifications`

```
Request:
  target: {
    type: user | group | broadcast
    id?: int
    email?: string      # user
    slug?: string       # group
  }
  title: string, required, max 255
  body?: string, max 2000
  image_url?: https URL, max 2048
  sound?: string, max 64
  category?: string, max 64
  android_channel_id?: string, max 64
  data?: object
    # reserved: data.url (https URL), data.url_label (button label)
  channels?: (push|mail|sms)[]
  scheduled_at?: future datetime

Response 202:
  data: PushNotification resource (status pending|scheduled, …)
```

### Auth summary matrix

| Endpoint | Company HMAC | User token |
|----------|:------------:|:----------:|
| `PUT /api/v1/{slug}/sync` | ✅ required | ❌ |
| `POST /api/v1/{slug}/notifications` | ✅ required | ❌ |
| `GET /api/v1/{slug}/notifications` | ✅ | ❌ |
| `POST /api/webhooks/{slug}/push` | ✅ via `X-Signature` | ❌ |

---

## 13. Implementation checklist

Use this when wiring another product:

- [ ] Obtain company `slug` + `hmac_secret` from a cloudPusher Global Admin; store in your secrets manager
- [ ] Assign stable `external_id` values for every user and group you sync
- [ ] Implement `PUT /{slug}/sync` for users (event-driven upserts)
- [ ] Implement group sync with full `members` arrays when membership changes
- [ ] Never enable `delete_missing_*` on partial payloads
- [ ] Implement `POST /{slug}/notifications` with company HMAC
- [ ] Deduplicate outbound notifications in your DB before retrying POSTs
- [ ] Map business events → `title` / `body` / `data` / `channels`
- [ ] Handle 401 / 404 / 422 / 429 distinctly in your client
- [ ] Confirm Horizon is running in the target environment before go-live testing
- [ ] Confirm at least one test user has a registered device token (via the receiver app) before expecting push success
- [ ] Optionally poll `GET /notifications/{id}` for support dashboards
- [ ] Document which team owns rotating per-company secrets

---

## 14. What cloudPusher does *not* do for you

| Expectation | Reality |
|-------------|---------|
| Sync creates push-ready devices | No — devices come from the mobile/web app |
| Sync passwords enable mobile login | Synced users get a random password; mobile login needs the registration/approval flow or a password you set another way |
| Your system can create companies via API | No — only a Global Admin creates companies in Filament |
| `external_id` can be used as notification target | No — target by email, cloudPusher user id, or group slug/id |
| Retrying a send is safe | No — creates duplicates; dedupe on your side |
| Inactive company still accepts traffic | No — inactive → 404 |

---

## Appendix A — Local Postman

The repo includes `postman/Push Service API.postman_collection.json` for device tokens, notifications, and webhooks. **Directory sync is not in that collection yet**; use this document (or add those requests yourself) with variables:

| Variable | Source |
|----------|--------|
| `baseUrl` | e.g. `https://push-service.test` |
| `companySlug` | From Filament company record |
| `companyToken` | `hmac_secret` from Filament |

---

## Appendix B — Related docs in this monorepo

| Doc | Purpose |
|-----|---------|
| [`README.md`](../README.md) | Project overview + short API snippets |
| `cloudPusher-app/docs/backend-rich-push-implementation.md` | Rich image/sound payload expectations for the receiver |
| `cloudPusher-app/docs/ios-rich-push-notifications.md` | iOS APNs details |
| `cloudPusher-app/docs/android-rich-push-notifications.md` | Android FCM details |

---

## Appendix C — Quick reference cheat sheet

```
# 1. Company created by Global Admin in Filament → copy slug + hmac_secret

# 2. Sync
PUT  /api/v1/{slug}/sync
Authorization: Bearer $HMAC

# 3. Notify
POST /api/v1/{slug}/notifications
Authorization: Bearer $HMAC

# 3b. Webhook notify
POST /api/webhooks/{slug}/push
X-Signature: sha256=$(hmac_sha256_hex raw_body $HMAC)
```

That’s the full system-to-system surface for user sync and push notifications.
