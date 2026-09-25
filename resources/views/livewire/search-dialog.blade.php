<div
    x-data="{
        open: false,
        init() {
            this.$watch('open', (open) => {
                const dialog = this.$refs.dialog;

                if (open) {
                    dialog.showModal();
                    this.$nextTick(() => this.$refs.input?.focus());
                } else if (dialog.open) {
                    dialog.close();
                }
            });
        },
    }"
    @keydown.window.prevent.cmd.k="open = true"
    @keydown.window.prevent.ctrl.k="open = true"
>
    <button
        type="button"
        @click="open = true"
        class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 p-1.5 text-start text-sm whitespace-nowrap text-gray-600 hover:border-gray-400 sm:w-64 sm:justify-start sm:px-3 dark:border-gray-800 dark:text-gray-500 dark:hover:border-gray-700"
    >
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="4.25" stroke="currentColor" stroke-width="1.5" />
            <path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>

        <span class="hidden flex-1 sm:block">Search documentation…</span>

        <span class="sr-only sm:hidden">Search documentation</span>

        <kbd class="hidden rounded border border-gray-300 px-1 font-sans text-[0.65rem] text-gray-500 sm:block dark:border-gray-700 dark:text-gray-600">⌘K</kbd>
    </button>

    <dialog
        wire:ignore.self
        x-ref="dialog"
        @close="open = false"
        @click.self="open = false"
        class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-xl border border-gray-300 bg-white p-0 text-gray-800 shadow-2xl backdrop:bg-gray-950/40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
    >
        <div class="flex items-center gap-3 border-b border-gray-300 px-4 dark:border-gray-800">
            <svg class="h-4 w-4 shrink-0 text-gray-500" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="7" cy="7" r="4.25" stroke="currentColor" stroke-width="1.5" />
                <path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>

            <input
                x-ref="input"
                id="documentation-search"
                wire:model.live.debounce.200ms="query"
                type="search"
                placeholder="Search every project…"
                autocomplete="off"
                class="w-full bg-transparent py-3.5 text-sm outline-none placeholder:text-gray-500 dark:placeholder:text-gray-600"
            />
        </div>

        <div class="max-h-[60vh] overflow-y-auto p-2">
            @forelse ($results as $result)
                <a
                    href="{{ $result->url }}"
                    class="block rounded-lg px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    <p class="flex flex-wrap items-center gap-x-2 text-xs tracking-wide text-gray-600 dark:text-gray-500">
                        <span class="uppercase">{{ $result->projectName }}</span>

                        @if ($result->showsVersion)
                            <span aria-hidden="true">&middot;</span>
                            <span>{{ $result->version }}</span>
                        @endif

                        @if ($result->status->showsLegacyNotice())
                            <span aria-hidden="true">&middot;</span>
                            <span class="text-amber-700 uppercase dark:text-amber-500">Legacy</span>
                        @endif
                    </p>

                    <p class="mt-1 text-sm font-medium">
                        {{ $result->pageTitle }}

                        @if ($result->heading)
                            <span class="text-gray-500 dark:text-gray-600">&rsaquo;</span>
                            <span class="font-normal text-gray-700 dark:text-gray-400">{{ $result->heading }}</span>
                        @endif
                    </p>

                    <p class="mt-0.5 line-clamp-2 text-sm text-gray-600 dark:text-gray-500">{{ $result->excerpt }}</p>
                </a>
            @empty
                <p class="px-3 py-6 text-center text-sm text-gray-600 dark:text-gray-500">
                    @if (mb_strlen(mb_trim($query)) < 2)
                        Search across every project.
                    @else
                        Nothing matches &ldquo;{{ $query }}&rdquo;.
                    @endif
                </p>
            @endforelse
        </div>
    </dialog>
</div>
