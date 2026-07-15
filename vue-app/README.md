# cloudPusher Receiver (Ionic Vue)

Cross-platform push notification receiver for the cloudPusher Laravel backend. Runs as:

- **iOS** native app (Capacitor + APNs)
- **Android** native app (Capacitor + FCM)
- **PWA** in the browser (Firebase Cloud Messaging + service worker)

## Features

- Push notification registration with the cloudPusher API
- Inbox UI with unread badges and pull-to-refresh
- **Sound on receive** (foreground app audio + native system sound)
- Haptic feedback on native platforms
- Offline cache of recent notifications
- Sync inbox from server on launch

## Setup

```bash
cd vue-app
cp .env.example .env
npm install
npm run dev
```

**Register** with company slug + name + email + mobile + password. A company admin must approve you (Filament **Registrations**, or the app **Approvals** tab).

**Sign in** with mobile number + password. The API host is hardcoded to `https://push-service.test`. In `npm run dev`, API calls are proxied (TLS verify off) so Herd cert warnings are ignored. After login the app stores your company from the authenticated user. Device preferences live under **Settings**.

## Environment variables

```env
# Required for PWA / web push only
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_AUTH_DOMAIN=
VITE_FIREBASE_PROJECT_ID=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
VITE_FIREBASE_VAPID_KEY=
```

Firebase config is written to `public/firebase-config.js` at build/dev start for the service worker.

## Native builds

```bash
npm run build
npx cap sync

# iOS
npx cap add ios   # first time
npx cap open ios

# Android
npx cap add android   # first time
npx cap open android
```

### iOS requirements

- Enable Push Notifications capability in Xcode
- Upload APNs key to Laravel backend (`PUSH_APNS_ENABLED=true`)
- Device tokens register as `apns` platform

### Android requirements

- Add `google-services.json` to the Android project
- Configure Firebase in Laravel (`PUSH_FCM_ENABLED=true`)
- Device tokens register as `fcm` platform

## PWA install

```bash
npm run build
npm run preview
```

Install from the browser menu. Background notifications require Firebase configuration and a valid VAPID key.

## Sound

- Foreground: plays `/public/sounds/notification.mp3` when a push arrives
- Background (native): system notification sound via APNs/FCM `sound` payload from backend
- Toggle in Settings → "Play sound on receive"

## API endpoints used

- `POST /api/v1/{company}/device-tokens` — register push token
- `GET /api/v1/{company}/inbox?user[email]=...` — sync stored notifications (`delivered_at`, `read_at`)
- `PATCH /api/v1/{company}/inbox/{id}/read` — mark one notification read
- `PATCH /api/v1/{company}/inbox/read-all` — mark all read
