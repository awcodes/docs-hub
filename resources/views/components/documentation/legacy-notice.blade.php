@props(['project', 'version', 'currentVersion'])

@if ($currentVersion && $version->status->showsLegacyNotice())
    <aside class="mb-8 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900">
        <p>You are viewing {{ $project->name }} {{ $version->version }} documentation.</p>

        <p class="mt-1 text-gray-700 dark:text-gray-400">
            {{ $project->name }} {{ $currentVersion['version'] }} is the current version.
            <a
                href="{{ $currentVersion['url'] }}"
                class="text-primary-700 dark:text-primary-400 underline underline-offset-4"
                >View {{ $currentVersion['version'] }} documentation</a
            >.
        </p>
    </aside>
@endif
