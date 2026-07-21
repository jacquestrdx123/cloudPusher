<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Request access to cloudPusher — a closed-circuit notification platform.">

        <title>Contact us — cloudPusher</title>

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
                <div class="mx-auto grid w-full max-w-5xl gap-12 lg:grid-cols-2 lg:items-start lg:gap-16">
                    <div class="max-w-lg">
                        <p class="animate-signal-rise text-sm font-medium tracking-wide text-signal-teal uppercase">
                            Contact us
                        </p>

                        <h1 class="animate-signal-rise font-display mt-3 text-4xl leading-none font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                            cloudPusher
                        </h1>

                        <p class="animate-signal-rise-delay mt-6 text-lg leading-relaxed text-signal-mist/85">
                            cloudPusher is a closed-circuit system. Access is not open for self-signup.
                        </p>

                        <p class="animate-signal-rise-delay mt-4 text-base leading-relaxed text-signal-mist/70">
                            For your company to use it, we must register you. Tell us who you are and we will follow up.
                        </p>

                        <a
                            href="{{ route('home') }}"
                            class="animate-signal-rise-delay-2 mt-8 inline-flex items-center gap-2 text-sm text-signal-mist/60 transition-colors hover:text-signal-mist"
                        >
                            <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M13 8H3M7 4l-4 4 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Back home
                        </a>
                    </div>

                    <div class="animate-signal-rise-delay-2 w-full max-w-md lg:max-w-none lg:justify-self-end">
                        @if (session('status'))
                            <div
                                class="mb-6 border border-signal-teal/40 bg-signal-deep/60 px-4 py-3 text-sm text-signal-mist"
                                role="status"
                            >
                                {{ session('status') }}
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('contact.store') }}"
                            class="space-y-5 border border-signal-mist/15 bg-signal-deep/40 p-6 backdrop-blur-sm sm:p-8"
                        >
                            @csrf

                            <div>
                                <label for="name" class="mb-1.5 block text-sm font-medium text-signal-mist/90">Name</label>
                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    value="{{ old('name') }}"
                                    required
                                    autocomplete="name"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                                @error('name')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-signal-mist/90">Email</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="email"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                                @error('email')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="company_name" class="mb-1.5 block text-sm font-medium text-signal-mist/90">Company name</label>
                                <input
                                    id="company_name"
                                    name="company_name"
                                    type="text"
                                    value="{{ old('company_name') }}"
                                    required
                                    autocomplete="organization"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                                @error('company_name')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="mb-1.5 block text-sm font-medium text-signal-mist/90">
                                    Phone <span class="font-normal text-signal-mist/45">(optional)</span>
                                </label>
                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    value="{{ old('phone') }}"
                                    autocomplete="tel"
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >
                                @error('phone')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="message" class="mb-1.5 block text-sm font-medium text-signal-mist/90">Message</label>
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="4"
                                    required
                                    class="w-full border border-signal-mist/20 bg-signal-ink/80 px-3 py-2.5 text-signal-mist placeholder:text-signal-mist/35 focus:border-signal-teal focus:outline-none"
                                >{{ old('message') }}</textarea>
                                @error('message')
                                    <p class="mt-1.5 text-sm text-signal-coral">{{ $message }}</p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 bg-signal-coral px-6 py-3 font-display text-sm font-semibold tracking-wide text-white uppercase transition-[background-color,transform] duration-200 hover:bg-signal-coral-hot hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-signal-mist sm:w-auto"
                            >
                                Request access
                            </button>
                        </form>
                    </div>
                </div>
            </main>

            <footer class="relative z-10 border-t border-signal-mist/10 px-6 py-4 text-xs text-signal-mist/45 sm:px-10 lg:px-16 xl:px-24">
                <p>Outbound messaging for your products — FCM, APNs, mail &amp; SMS.</p>
            </footer>
        </div>
    </body>
</html>
