<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Enums\DocumentationSnapshotState;
use App\Models\DocumentationSnapshot;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublishSnapshot
{
    public function handle(PackageVersion $packageVersion, DocumentationSnapshot $snapshot): PackageVersion
    {
        return Cache::lock("docs:publish:{$packageVersion->getKey()}", 15)
            ->block(5, fn (): PackageVersion => DB::transaction(
                fn (): PackageVersion => $this->publish($packageVersion, $snapshot),
                attempts: 3,
            ));
    }

    private function publish(PackageVersion $packageVersion, DocumentationSnapshot $snapshot): PackageVersion
    {
        $lockedVersion = PackageVersion::query()
            ->lockForUpdate()
            ->findOrFail($packageVersion->getKey());

        $lockedSnapshot = DocumentationSnapshot::query()
            ->lockForUpdate()
            ->findOrFail($snapshot->getKey());

        if ($lockedSnapshot->package_version_id !== $lockedVersion->getKey()) {
            throw new LogicException('A documentation snapshot can only be published for its owning package version.');
        }

        if ($lockedSnapshot->state !== DocumentationSnapshotState::Ready) {
            throw new LogicException('Only a ready documentation snapshot can be published.');
        }

        $activeSnapshot = $lockedVersion->activeSnapshot()
            ->lockForUpdate()
            ->first();

        if ($activeSnapshot !== null && $activeSnapshot->created_at->isAfter($lockedSnapshot->created_at)) {
            throw new LogicException('An older documentation snapshot cannot replace a newer active snapshot.');
        }

        $activeSnapshot?->update([
            'state' => DocumentationSnapshotState::Superseded,
        ]);

        $publishedAt = now();

        $lockedSnapshot->update([
            'state' => DocumentationSnapshotState::Active,
            'published_at' => $publishedAt,
        ]);

        $lockedVersion->update([
            'active_snapshot_id' => $lockedSnapshot->getKey(),
            'documentation_type' => $lockedSnapshot->documentation_type,
            'source_commit' => $lockedSnapshot->source_commit,
            'last_synced_at' => $publishedAt,
            'last_attempted_sync_at' => $publishedAt,
            'last_sync_error' => null,
        ]);

        return $lockedVersion->refresh();
    }
}
