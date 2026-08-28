<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Validation\ValidateDocumentationCheckout;
use App\Models\DocumentationSnapshot;
use App\Models\PackageVersion;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class SyncLocalDocumentation
{
    public function __construct(
        private ValidateDocumentationCheckout $validator,
        private BuildDocumentationSnapshot $builder,
        private PublishSnapshot $publisher,
        private FilesystemManager $filesystems,
    ) {}

    public function handle(PackageVersion $packageVersion, string $checkoutPath, string $sourceCommit): PackageVersion
    {
        $sourceCommit = mb_strtolower($sourceCommit);

        return Cache::lock("docs:sync:{$packageVersion->getKey()}", 60)->block(
            10,
            fn (): PackageVersion => $this->synchronize($packageVersion, $checkoutPath, $sourceCommit),
        );
    }

    private function synchronize(PackageVersion $packageVersion, string $checkoutPath, string $sourceCommit): PackageVersion
    {
        $packageVersion->refresh();

        if ($packageVersion->source_commit === $sourceCommit && $packageVersion->active_snapshot_id !== null) {
            return $packageVersion;
        }

        $packageVersion->update([
            'last_attempted_sync_at' => now(),
            'last_sync_error' => null,
        ]);

        $candidate = null;

        try {
            $this->removeStaleCandidate($packageVersion, $sourceCommit);
            $validated = $this->validator->handle($checkoutPath, $packageVersion->package->docs_path);
            $candidate = $this->builder->handle($packageVersion, $validated, $sourceCommit);

            return $this->publisher->handle($packageVersion, $candidate);
        } catch (Throwable $throwable) {
            if ($candidate instanceof DocumentationSnapshot && $candidate->fresh()?->isNot($packageVersion->activeSnapshot()->first())) {
                $this->filesystems->disk($candidate->storage_disk)->deleteDirectory($candidate->storage_prefix);
                $candidate->delete();
            }

            $packageVersion->update([
                'last_attempted_sync_at' => now(),
                'last_sync_error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    private function removeStaleCandidate(PackageVersion $packageVersion, string $sourceCommit): void
    {
        $snapshot = $packageVersion->snapshots()
            ->where('source_commit', $sourceCommit)
            ->whereKeyNot($packageVersion->active_snapshot_id)
            ->first();

        if (! $snapshot instanceof DocumentationSnapshot) {
            return;
        }

        $this->filesystems->disk($snapshot->storage_disk)->deleteDirectory($snapshot->storage_prefix);
        $snapshot->delete();
    }
}
