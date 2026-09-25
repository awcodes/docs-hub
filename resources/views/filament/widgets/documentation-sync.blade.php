<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Documentation</x-slot>

        <x-slot name="description">Synchronization is manual, so nothing is republished until it is asked for.</x-slot>

        <x-slot name="afterHeader">{{ $this->syncAllAction }}</x-slot>

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Projects</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $this->getProjectCount() }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Versions</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $this->getVersionCount() }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Never synced</dt>
                <dd @class([
                    'mt-1 text-2xl font-semibold tabular-nums',
                    'text-danger-600 dark:text-danger-400' => $this->getNeverSyncedCount() > 0,
                ])>
                    {{ $this->getNeverSyncedCount() }}
                </dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Last synced</dt>
                <dd class="mt-1 text-2xl font-semibold" title="{{ $this->getLastSyncedAt()?->toDayDateTimeString() }}">
                    {{ $this->getLastSyncedAt()?->diffForHumans(short: true) ?? 'Never' }}
                </dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
