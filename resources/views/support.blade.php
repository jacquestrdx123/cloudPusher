<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Support for the cloudPusher app — sign in, registration, account deletion, and help.">

        <title>Support — cloudPusher</title>

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
                        Help
                    </p>

                    <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                        Support
                    </h1>

                    <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/80">
                        Help for the cloudPusher iPhone and iPad app. If something is not working,
                        start here or contact us with the form linked below.
                    </p>

                    <div class="animate-signal-rise-delay mt-10 space-y-8 text-base leading-relaxed text-signal-mist/85">
                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Sign in and registration</h2>
                            <p>
                                Create an account and sign in inside the cloudPusher app. Use your mobile
                                number and password. New registrations need approval from a company admin
                                before sign-in works.
                            </p>
                            <p>
                                If Sign in fails, check your connection and try again. The app will show an
                                error if the service is temporarily unavailable.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Notifications</h2>
                            <p>
                                After you sign in, enable push notifications from Settings. Your company
                                administrator sends alerts to the inbox on this device.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Send messages</h2>
                            <p>
                                Open the <strong class="font-medium text-white">Send</strong> tab to message
                                one colleague, several people, a group, or everyone in your company. Recipients
                                get a push notification and the message appears in their inbox.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Add team members</h2>
                            <p>
                                Company admins can add people from the <strong class="font-medium text-white">Approvals</strong>
                                tab. Enter their name, mobile number, and e-mail, and choose whether they should also be a
                                company admin. New people receive an invitation link to set their own password; the link
                                expires after {{ trans_choice(':count day|:count days', max(1, (int) config('pushservice.invitation_ttl_days', 7))) }} and can be resent or revoked from the pending invitations list.
                                People who already use cloudPusher are added straight away.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Delete your account</h2>
                            <p>
                                You can permanently delete your account in the app under
                                <strong class="font-medium text-white">Settings → Delete account</strong>.
                                Deletion removes your profile, device tokens, and company memberships.
                            </p>
                        </section>

                        <section class="space-y-3">
                            <h2 class="font-display text-xl font-semibold text-white">Contact us</h2>
                            <p>
                                Use the
                                <a href="{{ route('contact') }}" class="text-signal-teal underline-offset-2 hover:underline">contact form</a>
                                for access requests, account issues, or privacy questions. You can also ask
                                the administrator who invited you to cloudPusher.
                            </p>
                            @if (filled($supportEmail = config('pushservice.support_email')))
                                <p>
                                    E-mail support directly at
                                    <a href="mailto:{{ $supportEmail }}" class="text-signal-teal underline-offset-2 hover:underline">{{ $supportEmail }}</a>.
                                    We reply within two business days.
                                </p>
                            @endif
                            <p>
                                Privacy details are on our
                                <a href="{{ route('privacy') }}" class="text-signal-teal underline-offset-2 hover:underline">privacy policy</a>.
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
                <p>
                    <a href="{{ route('support') }}" class="hover:text-signal-mist">Support</a>
                    ·
                    <a href="{{ route('privacy') }}" class="hover:text-signal-mist">Privacy</a>
                    ·
                    <a href="{{ route('contact') }}" class="hover:text-signal-mist">Contact</a>
                </p>
            </footer>
        </div>
    </body>
</html>
