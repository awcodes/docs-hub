<?php

declare(strict_types=1);

use App\Documentation\Actions\PublishSnapshot;
use App\Documentation\Markdown\MarkdownRenderer;
use App\Documentation\Search\DocumentationIndexer;
use App\Documentation\Storage\SnapshotStore;
use App\Models\DocumentationPage;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/** Where fixture documentation trees are built and torn down. */
function documentationScratch(): string
{
    return scratchRoot().'/docs-hub-published';
}

/**
 * The temporary directory this test process owns.
 *
 * Scoped to the parallel-testing token, so that one process cleaning up its
 * fixtures never deletes another's.
 */
function scratchRoot(): string
{
    return sys_get_temp_dir().'/docs-hub-tests-'.(Illuminate\Support\Facades\ParallelTesting::token() ?: 'serial');
}

/**
 * A project with one published version: files on disk, a snapshot, an index.
 *
 * Shared because everything downstream of synchronization needs a version that
 * has actually published something, and synchronization itself is not built
 * yet. Writing the snapshot and the index directly is the same state a sync
 * would leave behind.
 *
 * @param  array<string, string>  $pages  URL reference => Markdown
 * @param  list<array{label?: string, page?: string, children?: list<string>}>|null  $navigation
 *                                                                                                The stored manifest tree. Defaults to every page in the order given, which
 *                                                                                                is what a sync leaves behind for a flat `docs.yml`.
 */
function publishDocumentation(
    array $pages,
    string $slug = 'example',
    string $version = '1.x',
    bool $rolling = false,
    ?array $navigation = null,
): ProjectVersion {
    $project = $rolling
        ? Project::factory()->application()->create(['slug' => $slug])
        : Project::factory()->create(['slug' => $slug]);

    $record = ProjectVersion::factory()->for($project)->default()->create(['version' => $version]);

    $root = documentationScratch().'/'.Str::random(12);

    File::ensureDirectoryExists("{$root}/docs");
    File::put("{$root}/docs/docs.yml", "navigation:\n  - index");

    foreach ($pages as $reference => $markdown) {
        File::ensureDirectoryExists(dirname("{$root}/docs/{$reference}.md"));
        File::put("{$root}/docs/{$reference}.md", $markdown);

        $page = DocumentationPage::factory()->for($record, 'version')->create([
            'slug' => $reference,
            'source_path' => "docs/{$reference}.md",
            'title' => (new MarkdownRenderer)->render($markdown)->title()
                ?? Str::headline(basename($reference)),
        ]);

        // Indexed here because a sync indexes: a helper that left a different
        // state than the pipeline does would make every test downstream of it
        // a test of the helper.
        app(DocumentationIndexer::class)->index($page, $markdown);
    }

    $commit = mb_substr(hash('sha256', $slug.$version.implode('', array_keys($pages))), 0, 12);

    (new SnapshotStore)->write($record, $commit, $root);
    (new PublishSnapshot)->handle($record, $commit);

    $record->forceFill([
        'navigation' => $navigation ?? array_map(
            static fn (string $reference): array => ['page' => $reference],
            array_keys($pages),
        ),
    ])->save();

    return $record->refresh();
}

/**
 * A registered version whose source is a checkout we control.
 *
 * @param  array<string, string>  $files  repository-relative path => contents
 */
function mounted(array $files, string $slug = 'example', string $version = '1.x'): ProjectVersion
{
    $project = Project::factory()->create(['slug' => $slug]);
    $record = ProjectVersion::factory()->for($project)->default()->create(['version' => $version]);

    config()->set('documentation.local_sources', [$slug => checkout($slug, $files)]);

    return $record;
}

/** @param array<string, string> $files */
function checkout(string $slug, array $files): string
{
    $root = documentationScratch().'/'.$slug;

    File::deleteDirectory($root);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    return $root;
}
