<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Documentation\Actions\SyncVersions;
use App\Documentation\Data\SyncAttempt;
use App\Documentation\Data\SyncSummary;
use App\Models\ProjectVersion;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Running `docs:sync` from the panel, and saying what happened.
 *
 * Shared by the dashboard widget and the per-project action so the two cannot
 * describe the same synchronization differently. Neither decides what a sync
 * does — `SyncVersions` does, and the console calls the same thing.
 *
 * The work happens in the request. Nothing here is queued yet, and a
 * synchronization that
 * silently returned before it finished would be worse than one that takes a
 * moment.
 */
trait SynchronizesDocumentation
{
    /** @param Collection<int, ProjectVersion> $versions */
    protected function synchronize(Collection $versions): void
    {
        if ($versions->isEmpty()) {
            Notification::make()
                ->title('Nothing to synchronize')
                ->body('No versions are registered, so there is nothing to publish.')
                ->warning()
                ->send();

            return;
        }

        $this->announce(app(SyncVersions::class)->handle($versions));
    }

    private function announce(SyncSummary $summary): void
    {
        $notification = Notification::make()->title($summary->headline());

        if (! $summary->hasFailures()) {
            $notification->success()->send();

            return;
        }

        // Named, because "1 failed" is not something anyone can act on. The
        // previous snapshot is still serving in every one of these cases.
        $notification
            ->danger()
            ->body(collect($summary->failures())
                ->map(fn (SyncAttempt $attempt): string => "{$attempt->label()}: {$attempt->summary()}")
                ->implode("\n"))
            ->persistent()
            ->send();
    }
}
