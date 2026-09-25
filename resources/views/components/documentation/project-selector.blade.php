@props(['project', 'projects'])

<details data-dismissible class="group/projects relative">
    <summary class="hover:text-primary-700 dark:hover:text-primary-400 flex cursor-pointer list-none items-center gap-1.5 font-semibold marker:hidden">
        {{ $project->name }}

        <svg class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform group-open/projects:rotate-180 dark:text-gray-600" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M3 4.5 6 7.5 9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <span class="sr-only">Switch project</span>
    </summary>

    <div class="absolute start-0 top-full z-20 mt-2 max-h-80 w-60 overflow-y-auto rounded-lg border border-gray-300 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
        @foreach ($projects as $group => $entries)
            <p class="px-2 pt-2 pb-1 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-500">
                {{ $group }}
            </p>

            <ul class="mb-1 text-sm">
                @foreach ($entries as $entry)
                    <li>
                        <a
                            href="{{ $entry['url'] }}"
                            @class([
                                'block rounded px-2 py-1.5',
                                'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-400' => $entry['current'],
                                'hover:bg-gray-50 dark:hover:bg-gray-800' => ! $entry['current'],
                            ])
                            @if ($entry['current']) aria-current="true" @endif
                        >{{ $entry['name'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </div>
</details>
