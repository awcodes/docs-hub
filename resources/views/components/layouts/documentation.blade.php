@props([
    'project' => null,
    'version' => null,
    'title' => null,
])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ $title ? $title . ' · ' : '' }}{{ $project?->name ?? config('app.name') }}</title>

    <link rel="icon" type="image/svg" href="{{ asset('favicon.svg') }}" />

    <script>
        (() => {
            let choice = null;

            try {
                choice = localStorage.getItem('docs-theme');
            } catch {
                // Private windows and blocked site data both throw here. The
                // system preference is a perfectly good answer.
            }

            document.documentElement.dataset.theme =
                choice ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="h-full bg-white text-gray-800 antialiased dark:bg-gray-950 dark:text-gray-300">
    <div class="flex min-h-full flex-col" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
        <header class="sticky top-0 z-10 border-b border-gray-300 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-950/95">
            <div class="mx-auto flex h-14 max-w-[100rem] items-center gap-4 px-4 sm:px-6">
                @if (isset($navigation))
                    <button
                        type="button"
                        @click="drawer = true"
                        class="-ms-1 shrink-0 p-1 text-gray-700 lg:hidden dark:text-gray-400"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>

                        <span class="sr-only">Open navigation</span>
                    </button>
                @endif

                <div class="size-6 shrink-0">
                    <x-logo.icon />
                </div>

                <a href="/" class="text-sm font-semibold">Docs Hub</a>

                <div class="ms-auto flex items-center gap-4 text-sm">
                    <livewire:search-dialog />

                    <x-documentation.theme-toggle />

                    @if ($project)
                        <a
                            href="https://github.com/{{ $project->repository }}"
                            class="hidden text-gray-700 hover:text-gray-900 sm:inline dark:text-gray-400 dark:hover:text-gray-100"
                            rel="noopener noreferrer"
                            target="_blank"
                        >GitHub</a>
                    @endif

                    @auth
                        <a
                            href="{{ \Filament\Facades\Filament::getDefaultPanel()->getUrl() }}"
                            class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >Admin</a>

                        <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            >
                                Sign out
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </header>

        <div
            x-show="drawer"
            x-transition.opacity
            @click="drawer = false"
            class="fixed inset-0 z-30 bg-gray-950/40 lg:hidden"
            x-cloak
        ></div>

        <div
            x-show="drawer"
            x-transition:enter-start="-translate-x-full"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 start-0 z-40 flex w-72 flex-col overflow-y-auto border-e border-gray-300 bg-white p-6 transition lg:hidden dark:border-gray-800 dark:bg-gray-950"
            x-cloak
        >
            <div class="mb-4 flex justify-end">
                <button type="button" @click="drawer = false" class="p-1 text-gray-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                    </svg>

                    <span class="sr-only">Close navigation</span>
                </button>
            </div>

            {{ $context ?? '' }} {{ $navigation ?? '' }}
        </div>

        <div class="mx-auto flex w-full max-w-[100rem] flex-1 gap-8 px-4 sm:px-6">
            @if (isset($navigation))
                <nav
                    class="sticky top-14 z-20 hidden h-[calc(100vh-3.5rem)] w-56 shrink-0 flex-col py-10 lg:flex"
                    aria-label="{{ $project?->name }} documentation"
                >
                    <div class="shrink-0">{{ $context ?? '' }}</div>

                    <div class="min-h-0 flex-1 overflow-y-auto">{{ $navigation }}</div>
                </nav>
            @endif

            <main class="min-w-0 flex-1 py-10">
                @if (isset($contents) && ! $contents->isEmpty())
                    <details class="mb-8 rounded-lg border border-gray-300 px-4 py-2 xl:hidden dark:border-gray-800">
                        <summary class="cursor-pointer list-none text-xs font-semibold tracking-wide text-gray-600 uppercase marker:hidden dark:text-gray-500">
                            On this page
                        </summary>

                        <div class="pt-3 pb-1">{{ $contents }}</div>
                    </details>
                @endif

                {{ $slot }}
            </main>

            @if (isset($contents) && ! $contents->isEmpty())
                <aside
                    class="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-56 shrink-0 overflow-y-auto py-10 xl:block"
                    aria-label="Table of contents"
                >
                    {{ $contents }}
                </aside>
            @endif
        </div>
    </div>
    @livewireScripts
</body>
</html>
