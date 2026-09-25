<button
    type="button"
    x-data
    @click="
        const dark = document.documentElement.dataset.theme !== 'dark';
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        try {
            localStorage.setItem('docs-theme', dark ? 'dark' : 'light');
        } catch {}
    "
    class="text-gray-700 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
>
    <svg class="h-4 w-4 dark:hidden" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M13 9.3A5.2 5.2 0 0 1 6.7 3a5.5 5.5 0 1 0 6.3 6.3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
    </svg>

    <svg class="hidden h-4 w-4 dark:block" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.4" />
        <path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1m11-4.9-1 1M5.1 10.9l-1 1m7.8 0-1-1M5.1 5.1l-1-1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
    </svg>

    <span class="sr-only">Toggle dark mode</span>
</button>
