<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\DocumentationManifest;
use App\Documentation\Data\DocumentationNavigationItem as NavigationData;
use App\Documentation\Data\ValidatedDocumentationCheckout;
use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Markdown\MarkdownPageParser;
use App\Documentation\Support\DocumentationPathResolver;
use App\Enums\DocumentationSnapshotState;
use App\Enums\DocumentationType;
use App\Models\DocumentationNavigationItem;
use App\Models\DocumentationPage;
use App\Models\DocumentationSnapshot;
use App\Models\PackageVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final readonly class BuildDocumentationSnapshot
{
    public function __construct(
        private FilesystemManager $filesystems,
        private MarkdownPageParser $markdownParser,
        private DocumentationPathResolver $pathResolver,
    ) {}

    public function handle(PackageVersion $packageVersion, ValidatedDocumentationCheckout $validated, string $sourceCommit, ?string $disk = null): DocumentationSnapshot
    {
        $sourceCommit = mb_strtolower($sourceCommit);

        if (preg_match('/\A[a-f0-9]{40}\z/', $sourceCommit) !== 1) {
            throw new DocumentationValidationException(['A source commit must be a complete 40-character Git SHA.']);
        }

        $disk ??= config('filesystems.default');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('The documentation snapshot disk is not configured.');
        }

        $storagePrefix = "docs/{$packageVersion->package_id}/{$packageVersion->getKey()}/{$sourceCommit}";
        $filesystem = $this->filesystems->disk($disk);
        $snapshot = $packageVersion->snapshots()->create([
            'source_commit' => $sourceCommit,
            'storage_disk' => $disk,
            'storage_prefix' => $storagePrefix,
            'documentation_type' => $validated->checkout->type,
            'state' => DocumentationSnapshotState::Building,
        ]);

        try {
            $this->copySource($validated, $filesystem, $storagePrefix);

            DB::transaction(function () use ($snapshot, $validated): void {
                $this->persistMetadata($snapshot, $validated);
                $snapshot->update(['state' => DocumentationSnapshotState::Ready]);
            });
        } catch (Throwable $throwable) {
            $filesystem->deleteDirectory($storagePrefix);
            $snapshot->delete();
            throw $throwable;
        }

        return $snapshot->refresh();
    }

    private function copySource(ValidatedDocumentationCheckout $validated, Filesystem $filesystem, string $storagePrefix): void
    {
        if ($validated->checkout->type === DocumentationType::None) {
            return;
        }

        if ($validated->checkout->type === DocumentationType::Readme) {
            $this->copyFile($filesystem, $validated->checkout->readmePath ?? '', "{$storagePrefix}/README.md");

            return;
        }

        $documentationRoot = $validated->checkout->documentationRoot;

        if ($documentationRoot === null) {
            throw new RuntimeException('Structured documentation has no source root.');
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentationRoot));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $relativePath = str_replace($documentationRoot.'/', '', $file->getPathname());
            $this->copyFile($filesystem, $file->getPathname(), "{$storagePrefix}/{$relativePath}");
        }
    }

    private function copyFile(Filesystem $filesystem, string $source, string $destination): void
    {
        $stream = fopen($source, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open documentation source [{$source}].");
        }

        try {
            if (! $filesystem->put($destination, $stream)) {
                throw new RuntimeException("Unable to write documentation snapshot file [{$destination}].");
            }
        } finally {
            fclose($stream);
        }
    }

    private function persistMetadata(DocumentationSnapshot $snapshot, ValidatedDocumentationCheckout $validated): void
    {
        $pageModels = [];
        $navigationOrder = $this->navigationOrder($validated);

        foreach ($validated->pages as $sourceReference => $metadata) {
            $isReadme = $validated->checkout->type === DocumentationType::Readme;
            $sourcePath = $isReadme ? 'README.md' : $this->pathResolver->sourcePath($sourceReference);
            $sourceFile = $isReadme ? $validated->checkout->readmePath : $validated->checkout->documentationRoot.'/'.$sourcePath;

            if ($sourceFile === null) {
                throw new RuntimeException("Unable to resolve source file [{$sourcePath}].");
            }

            $parsed = $this->markdownParser->parseFile($sourceFile, $metadata);
            $routePath = $isReadme ? '' : $this->pathResolver->routePath($sourceReference, $metadata->slug);
            $page = $snapshot->pages()->create([
                'source_path' => $sourcePath,
                'slug' => $metadata->slug,
                'route_path' => $routePath,
                'title' => $parsed->title,
                'description' => $metadata->description,
                'headings' => $parsed->headings,
                'content_hash' => $parsed->contentHash,
                'is_listed' => $isReadme || isset($navigationOrder[$sourceReference]),
                'navigation_order' => $navigationOrder[$sourceReference] ?? null,
            ]);

            foreach ($parsed->sections as $section) {
                $page->sections()->create($section + ['documentation_snapshot_id' => $snapshot->getKey()]);
            }

            $pageModels[$sourceReference] = $page;
        }

        if ($validated->manifest instanceof DocumentationManifest) {
            $position = 0;

            foreach ($validated->manifest->navigation as $item) {
                $this->persistNavigationItem($snapshot, $item, $pageModels, null, $position++);
            }

            foreach ($validated->manifest->redirects as $source => $destination) {
                $snapshot->redirects()->create(['source_route' => $source, 'destination_route' => $destination]);
            }
        }
    }

    /** @param array<string, DocumentationPage> $pages */
    private function persistNavigationItem(DocumentationSnapshot $snapshot, NavigationData $item, array $pages, ?DocumentationNavigationItem $parent, int $position): void
    {
        $page = $item->pageReference === null ? null : $pages[$item->pageReference];
        $segment = mb_str_pad((string) $position, 4, '0', STR_PAD_LEFT);
        $positionPath = ! $parent instanceof DocumentationNavigationItem ? $segment : $parent->position_path.'.'.$segment;
        $model = $snapshot->navigationItems()->create([
            'parent_id' => $parent?->getKey(),
            'title' => $item->label ?? $page?->title,
            'route_path' => $page?->route_path,
            'depth' => ! $parent instanceof DocumentationNavigationItem ? 0 : 1,
            'sort_order' => $position,
            'position_path' => $positionPath,
        ]);

        foreach ($item->children as $childPosition => $child) {
            $this->persistNavigationItem($snapshot, $child, $pages, $model, $childPosition);
        }
    }

    /** @return array<string, int> */
    private function navigationOrder(ValidatedDocumentationCheckout $validated): array
    {
        $order = [];

        if (! $validated->manifest instanceof DocumentationManifest) {
            return $order;
        }

        foreach ($validated->manifest->navigation as $item) {
            if ($item->pageReference !== null) {
                $order[$item->pageReference] = count($order);
            }

            foreach ($item->children as $child) {
                if ($child->pageReference !== null) {
                    $order[$child->pageReference] = count($order);
                }
            }
        }

        return $order;
    }
}
