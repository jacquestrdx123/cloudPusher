<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="cloudPusher — multi-channel push, email, and SMS delivery.">

        <title>cloudPusher</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh overflow-x-hidden bg-signal-ink font-sans text-signal-mist antialiased">
        <div class="relative flex min-h-dvh flex-col">
            {{-- Full-bleed signal field --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div
                    class="absolute inset-0"
                    style="background:
                        radial-gradient(ellipse 90% 70% at 72% 42%, rgb(31 138 128 / 0.38), transparent 55%),
                        radial-gradient(ellipse 55% 45% at 12% 78%, rgb(232 93 76 / 0.16), transparent 50%),
                        linear-gradient(165deg, #06141b 0%, #0a2a32 48%, #06141b 100%);"
                ></div>

                <div class="absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle at 1px 1px, #c8ebe6 1px, transparent 0); background-size: 28px 28px;"></div>

                <div class="animate-signal-drift absolute top-1/2 right-[-10%] aspect-square w-[min(98vw,46rem)] -translate-y-1/2 md:right-[-2%] lg:right-[2%]">
                    <span class="animate-signal-ripple absolute inset-[18%] rounded-[50%] border border-signal-teal/55"></span>
                    <span class="animate-signal-ripple-delay absolute inset-[8%] rounded-[50%] border border-signal-mist/20"></span>
                    <span class="animate-signal-ripple-delay-2 absolute inset-0 rounded-[50%] border border-signal-coral/28"></span>

                    <span class="absolute inset-[38%] rounded-[50%] border border-signal-teal/35 bg-signal-deep/50"></span>

                    <span class="absolute top-1/2 left-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center md:size-20">
                        <span class="animate-signal-pulse-dot absolute inset-0 rounded-[50%] bg-signal-coral/30"></span>
                        <span class="relative flex size-10 items-center justify-center rounded-[50%] bg-signal-coral md:size-12">
                            <svg class="size-5 text-white md:size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 12c3.5-4 5.5-6 8-6s4.5 2 8 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <path d="M7 15.5c2.2-2.5 3.4-3.5 5-3.5s2.8 1 5 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <circle cx="12" cy="19" r="1.4" fill="currentColor"/>
                            </svg>
                        </span>
                    </span>
                </div>
            </div>

            <main class="relative z-10 flex flex-1 flex-col justify-center px-6 py-16 sm:px-10 lg:px-16 xl:px-24">
                <div class="max-w-xl">
                    <h1 class="animate-signal-rise font-display text-5xl leading-none font-extrabold tracking-tight text-white sm:text-6xl lg:text-7xl xl:text-8xl">
                        cloudPusher
                    </h1>

                    <p class="animate-signal-rise-delay mt-5 max-w-md text-lg leading-relaxed text-signal-mist/80 sm:text-xl">
                        Multi-channel notifications that reach users over push, email, and SMS.
                    </p>

                    <div class="animate-signal-rise-delay-2 mt-10 flex flex-wrap items-center gap-4">
                        <a
                            href="{{ route('contact') }}"
                            class="inline-flex items-center gap-2 bg-signal-coral px-6 py-3 font-display text-sm font-semibold tracking-wide text-white uppercase transition-[background-color,transform] duration-200 hover:bg-signal-coral-hot hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-signal-mist"
                        >
                            Contact us
                            <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <a
                            href="/admin"
                            class="inline-flex items-center gap-2 border border-signal-mist/25 px-6 py-3 font-display text-sm font-semibold tracking-wide text-signal-mist uppercase transition-[border-color,color,transform] duration-200 hover:border-signal-mist/50 hover:text-white hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-signal-mist"
                        >
                            Admin login
                        </a>
                    </div>
                </div>
            </main>

            <footer class="relative z-10 border-t border-signal-mist/10 px-6 py-4 text-xs text-signal-mist/45 sm:px-10 lg:px-16 xl:px-24">
                <p>Outbound messaging for your products — FCM, APNs, mail &amp; SMS.</p>
            </footer>
        </div>
    </body>
</html>
