@props(['version', 'versions'])

@if (count($versions) > 1)
    <details data-dismissible class="group/versions relative mt-2.5">
        <summary class="flex cursor-pointer list-none items-center gap-1.5 text-sm marker:hidden">
            <span class="font-medium">{{ $version->version }}</span>
            <span class="text-xs text-gray-600 dark:text-gray-500">{{ $version->status->getLabel() }}</span>

            <svg class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform group-open/versions:rotate-180 dark:text-gray-600" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M3 4.5 6 7.5 9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <span class="sr-only">Switch version</span>
        </summary>

        <ul class="absolute start-0 top-full z-20 mt-2 w-52 rounded-lg border border-gray-300 bg-white p-2 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-900">
            @foreach ($versions as $option)
                <li>
                    <a
                        href="{{ $option['url'] }}"
                        @class([
                            'flex items-baseline justify-between gap-3 rounded px-2 py-1.5',
                            'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-400' => $option['current'],
                            'hover:bg-gray-50 dark:hover:bg-gray-800' => ! $option['current'],
                        ])
                        @if ($option['current']) aria-current="true" @endif
                    >
                        <span>{{ $option['version'] }}</span>
                        <span class="text-xs text-gray-600 dark:text-gray-500">{{ $option['status']->getLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@else
    <p class="mt-2.5 flex items-center gap-2 text-sm">
        <span class="font-medium">{{ $version->version }}</span>
        <span class="text-xs text-gray-600 dark:text-gray-500">{{ $version->status->getLabel() }}</span>
    </p>
@endif
