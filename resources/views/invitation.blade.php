<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="description" content="Set your cloudPusher password to accept your company invitation.">

        <title>Accept your invitation — cloudPusher</title>

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

            <main class="relative z-10 flex flex-1 flex-col justify-center px-6 py-16 sm:px-10 lg:px-16 xl:px-24">
                <div class="mx-auto w-full max-w-md">
                    <p class="animate-signal-rise text-sm font-medium tracking-wide text-signal-teal uppercase">
                        Invitation
                    </p>

                    @if ($state === 'form')
                        <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                            Welcome, {{ $invitation->user->name }}
                        </h1>

                        <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/80">
                            You have been added to <strong class="font-medium text-white">{{ $invitation->company->name }}</strong>
                            on cloudPusher. Choose a password to activate your account.
                        </p>

                        <form
                            method="POST"
                            action="{{ route('invitation.accept', $token) }}"
                            class="animate-signal-rise-delay-2 mt-8 space-y-5 border border-signal-mist/15 bg-signal-deep/40 p-6 backdrop-blur-sm sm:p-8"
                        >
                            @csrf

                            <div>
                                <p class="mb-1.5 text-sm font-medium text-signal-mist/90">Mobile number</p>
                                <p class="text-signal-mist">{{ $invitation->user->phone }}</p>
                                <p class="mt-1 text-xs text-signal-mist/55">You will sign in to the app with this number.</p>
                            </div>

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-signal-mist/90">New password</label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                                @error('password')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-signal-mist/90">Confirm password</label>
                                <input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 bg-signal-coral px-6 py-3 font-display text-sm font-semibold tracking-wide text-white uppercase transition-[background-color,transform] duration-200 hover:bg-signal-coral-hot hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-signal-mist"
                            >
                                Set password
                            </button>
                        </form>
                    @elseif ($state === 'accepted')
                        @php($accepted = session('invitation_accepted'))

                        <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                            You're all set
                        </h1>

                        <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/80">
                            @if (is_array($accepted))
                                Your password is saved and you are a member of
                                <strong class="font-medium text-white">{{ $accepted['company'] }}</strong>.
                            @else
                                Your password is saved.
                            @endif
                            Open the cloudPusher app and sign in with your mobile number
                            @if (is_array($accepted) && filled($accepted['phone']))
                                (<span class="text-white">{{ $accepted['phone'] }}</span>)
                            @endif
                            and your new password.
                        </p>
                    @elseif ($state === 'expired')
                        <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                            This link has expired
                        </h1>

                        <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/80">
                            Ask your company admin to resend your invitation from the cloudPusher app.
                        </p>
                    @else
                        <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl">
                            This link is not valid
                        </h1>

                        <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/80">
                            It may already have been used or been cancelled. If you have already set your password,
                            sign in to the cloudPusher app. Otherwise, ask your company admin for a new invitation.
                        </p>
                    @endif

                    <a
                        href="{{ route('support') }}"
                        class="animate-signal-rise-delay-2 mt-10 inline-flex items-center gap-2 text-sm text-signal-mist/60 transition-colors hover:text-signal-mist"
                    >
                        Need help? Visit support
                    </a>
                </div>
            </main>

            <footer class="relative z-10 border-t border-signal-mist/10 px-6 py-4 text-xs text-signal-mist/45 sm:px-10 lg:px-16 xl:px-24">
                <p>
                    <a href="{{ route('support') }}" class="hover:text-signal-mist">Support</a>
                    ·
                    <a href="{{ route('privacy') }}" class="hover:text-signal-mist">Privacy</a>
                </p>
            </footer>
        </div>
    </body>
</html>
