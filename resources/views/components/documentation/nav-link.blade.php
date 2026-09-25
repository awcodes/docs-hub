@props(['item'])

<a
    href="{{ $item['url'] }}"
    @class([
        'block',
        'font-medium text-primary-700 dark:text-primary-400' => $item['current'],
        'text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100' => ! $item['current'],
    ])
    @if ($item['current']) aria-current="page" @endif
>{{ $item['title'] }}</a>
