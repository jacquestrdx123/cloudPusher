<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Privacy policy for cloudPusher — how we collect, use, and delete your data.">

        <title>Privacy policy — cloudPusher</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh overflow-x-hidden bg-signal-ink font-sans text-signal-mist antialiased">
        <div class="relative flex min-h-dvh flex-col">
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div
                    class="absolute inset-0"
                    style="background:
                        radial-gradient(ellipse 90% 70% at 72% 42%, rgb(31 138 128 / 0.38), transparent 55%),
                        radial-gradient(ellipse 55% 45% at 12% 78%, rgb(232 93 76 / 0.16), transparent 50%),
                        linear-gradient(165deg, #06141b 0%, #0a2a32 48%, #06141b 100%);"
                ></div>

                <div class="absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle at 1px 1px, #c8ebe6 1px, transparent 0); background-size: 28px 28px;"></div>
            </div>

            <main class="relative z-10 flex flex-1 flex-col px-6 py-16 sm:px-10 lg:px-16 xl:px-24">
                <div class="mx-auto w-full max-w-3xl">
                    <p class="animate-signal-rise text-sm font-medium tracking-wide text-signal-teal uppercase">
                        Legal
                    </p>

                    <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                        Privacy policy
                    </h1>

                    <p class="animate-signal-rise-delay mt-4 text-sm text-signal-mist/55">
                        Last updated {{ now()->toFormattedDateString() }}
                    </p>

                    <div class="animate-signal-rise-delay mt-10 space-y-8 text-base leading-relaxed text-signal-mist/85">
                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Who we are</h2>
                            <p>
                                cloudPusher is a closed-circuit notification platform used by companies to send
                                push, email, and SMS messages to their members. This policy explains what we
                                collect when you use the cloudPusher mobile app or related services.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">What we collect</h2>
                            <ul class="list-disc space-y-2 pl-5 text-signal-mist/80">
                                <li>Account details you provide: name, email address, mobile phone number, and password (stored hashed).</li>
                                <li>Company membership information linking your account to one or more companies.</li>
                                <li>Device push tokens (APNs / FCM) so we can deliver notifications to your devices.</li>
                                <li>Notification content sent to you by your company, including delivery and read status.</li>
                                <li>Basic device preferences you set in the app (for example device name and sound settings), stored on your device.</li>
                            </ul>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">How we use your data</h2>
                            <p>
                                We use this information to authenticate you, deliver company notifications,
                                show your inbox, and operate the admin tools your company administrators use.
                                We do not sell your personal data.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Retention</h2>
                            <p>
                                Account and membership data are kept while your account is active.
                                Notification history is retained for operational purposes for your companies.
                                Device tokens are removed when you unregister a device or delete your account.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Deleting your account</h2>
                            <p>
                                You can permanently delete your account from the cloudPusher app under
                                <strong class="font-medium text-white">Settings → Delete account</strong>.
                                Deletion removes your user profile, API tokens, device tokens, and company
                                memberships. Related notification delivery records for your account are also removed.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Contact</h2>
                            <p>
                                For privacy questions or data requests, use our
                                <a href="{{ route('contact') }}" class="text-signal-teal underline-offset-2 hover:underline">contact form</a>.
                            </p>
                        </section>
                    </div>

                    <a
                        href="{{ route('home') }}"
                        class="animate-signal-rise-delay-2 mt-12 inline-flex items-center gap-2 text-sm text-signal-mist/60 transition-colors hover:text-signal-mist"
                    >
                        <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M13 8H3M7 4l-4 4 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Back home
                    </a>
                </div>
            </main>

            <footer class="relative z-10 border-t border-signal-mist/10 px-6 py-4 text-xs text-signal-mist/45 sm:px-10 lg:px-16 xl:px-24">
                <p>Outbound messaging for your products — FCM, APNs, mail &amp; SMS.</p>
            </footer>
        </div>
    </body>
</html>
