<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Exceptions\SnapshotFailed;
use App\Documentation\Storage\SnapshotStore;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\DB;

/**
 * Make a written snapshot the one a version serves.
 *
 * The entire publication step, and it is a column update. Nothing is moved,
 * renamed or copied: the snapshot was already whole and addressable before this
 * ran, so the only thing left is to say which one is live.
 *
 * Which is also why a failed synchronization costs nothing. The previous
 * snapshot keeps serving until this line runs, and if it never runs, readers
 * never see a half-written version.
 */
final readonly class PublishSnapshot
{
    public function __construct(
        private SnapshotStore $snapshots = new SnapshotStore,
    ) {}

    /**
     * @throws SnapshotFailed
     */
    public function handle(ProjectVersion $version, string $commit): ProjectVersion
    {
        if (! $this->snapshots->exists($version, $commit)) {
            throw SnapshotFailed::notWritten($commit);
        }

        // Read from the snapshot rather than taken on trust, so that the
        // stored type always describes what is actually on disk.
        $type = $this->snapshots->documentationType($version, $commit);

        DB::transaction(function () use ($version, $commit, $type): void {
            $version->forceFill([
                'active_snapshot' => $commit,
                'source_commit' => $commit,
                'documentation_type' => $type,
                'last_synced_at' => now(),
            ])->save();
        });

        /** @var int $retain */
        $retain = config('documentation.snapshots.retain', 3);

        // Pruning after the pointer moves, never before: a superseded snapshot
        // is what makes a rollback a pointer update instead of a
        // resynchronization, and the one thing that must never be deleted is
        // whatever is currently live.
        $this->snapshots->prune($version, $commit, $retain);

        return $version;
    }
}
