<x-layouts.documentation>
    <div class="mx-auto max-w-3xl">
        <header class="border-b border-gray-300 pb-8 dark:border-gray-800">
            <h1 class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                {{ config('app.name') }}
            </h1>

            <p class="mt-3 text-gray-700 dark:text-gray-400">Documentation for the awcodes open source packages.</p>

            <p class="mt-6 text-sm text-gray-600 dark:text-gray-500">
                Search every project with
                <kbd
                    class="rounded border border-gray-400 px-1.5 py-0.5 font-sans text-xs text-gray-700 dark:border-gray-700 dark:text-gray-400"
                    >&#8984;K</kbd
                >, or the control in the header.
            </p>
        </header>

        @forelse ($groups as $group => $projects)
            <section class="mt-10">
                <h2 class="text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-500">
                    {{ $group }}
                </h2>

                <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-900">
                    @foreach ($projects as $project)
                        <li>
                            <a
                                href="/{{ $project['slug'] }}"
                                class="group -mx-3 flex items-baseline gap-4 rounded-lg px-3 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-900/60"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-gray-900 group-hover:text-sky-700 dark:text-gray-100 dark:group-hover:text-sky-400">
                                        {{ $project['name'] }}
                                    </span>

                                    @if ($project['description'])
                                        <span class="mt-0.5 block text-sm text-gray-700 dark:text-gray-400">
                                            {{ $project['description'] }}
                                        </span>
                                    @endif
                                </span>

                                @if ($project['version'])
                                    <span class="shrink-0 text-xs text-gray-600 tabular-nums dark:text-gray-500">
                                        Current: {{ $project['version'] }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="mt-10 text-gray-700 dark:text-gray-400">
                Nothing is registered yet. Add a project in the operations panel, then synchronize it.
            </p>
        @endforelse
    </div>
</x-layouts.documentation>
