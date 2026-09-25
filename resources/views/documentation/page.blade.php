<x-layouts.documentation :project="$project" :version="$version" :title="$page->title">
    <x-slot:context>
        <div class="mb-6 border-b border-gray-300 pb-5 dark:border-gray-800">
            <x-documentation.project-selector :project="$project" :projects="$projects" />

            @if ($project->description)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-500">{{ $project->description }}</p>
            @endif

            @if ($project->versioning_mode->showsVersionSegment())
                <x-documentation.version-selector :version="$version" :versions="$versions" />
            @endif
        </div>
    </x-slot:context>

    <x-slot:navigation>
        @if ($navigation === [])
            <p class="text-sm text-gray-600 dark:text-gray-500">This version has no navigation of its own.</p>
        @endif

        <ul class="space-y-2 text-sm">
            @foreach ($navigation as $entry)
                @if ($entry['type'] === 'page')
                    <li>
                        <x-documentation.nav-link :item="$entry" />
                    </li>
                @else
                    <li class="pt-4 first:pt-0">
                        <p class="mb-1.5 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-500">
                            {{ $entry['label'] }}
                        </p>

                        <ul class="space-y-1.5 border-s border-gray-300 dark:border-gray-800">
                            @foreach ($entry['children'] as $child)
                                <li class="-ms-px border-s border-transparent ps-3 @if ($child['current']) border-primary-600 dark:border-primary-400 @endif">
                                    <x-documentation.nav-link :item="$child" />
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endforeach
        </ul>
    </x-slot:navigation>

    <x-documentation.legacy-notice :project="$project" :version="$version" :current-version="$currentVersion" />

    <article class="docs-prose">{!! $rendered->html !!}</article>

    <footer class="mt-14 border-t border-gray-300 pt-6 dark:border-gray-800">
        <p class="text-sm">
            <a
                href="{{ $source }}"
                class="text-gray-600 underline-offset-4 hover:text-gray-800 hover:underline dark:text-gray-500 dark:hover:text-gray-300"
                rel="noopener noreferrer"
                target="_blank"
            >Edit this page on GitHub</a>

            @if ($version->last_synced_at)
                <span class="text-gray-500 dark:text-gray-600">
                    &middot; synchronized {{ $version->last_synced_at->diffForHumans() }}
                </span>
            @endif
        </p>

        @if ($neighbours['previous'] || $neighbours['next'])
            <nav class="mt-6 flex flex-wrap gap-4" aria-label="Sequential">
                @if ($neighbours['previous'])
                    <a
                        href="{{ $neighbours['previous']['url'] }}"
                        class="group flex-1 rounded-lg border border-gray-300 px-4 py-3 hover:border-gray-400 dark:border-gray-800 dark:hover:border-gray-700"
                    >
                        <span class="block text-xs text-gray-600 dark:text-gray-500">&larr; Previous</span>
                        <span class="mt-0.5 block text-sm font-medium">{{ $neighbours['previous']['title'] }}</span>
                    </a>
                @endif

                @if ($neighbours['next'])
                    <a
                        href="{{ $neighbours['next']['url'] }}"
                        class="group flex-1 rounded-lg border border-gray-300 px-4 py-3 text-end hover:border-gray-400 dark:border-gray-800 dark:hover:border-gray-700"
                    >
                        <span class="block text-xs text-gray-600 dark:text-gray-500">Next &rarr;</span>
                        <span class="mt-0.5 block text-sm font-medium">{{ $neighbours['next']['title'] }}</span>
                    </a>
                @endif
            </nav>
        @endif
    </footer>

    <x-slot:contents>
        @if (count($rendered->headings) > 1)
            <div>
                <p class="mb-3 hidden text-xs font-semibold tracking-wide text-gray-600 uppercase xl:block dark:text-gray-500">
                    On this page
                </p>

                <ul class="space-y-1.5 text-sm">
                    @foreach (collect($rendered->headings)->where('level', '>', 1) as $heading)
                        <li @class(['ps-3' => $heading['level'] > 2])>
                            <a
                                href="#{{ $heading['anchor'] }}"
                                class="block text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            >{{ $heading['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-slot:contents>
</x-layouts.documentation>
