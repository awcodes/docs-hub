<?php

declare(strict_types=1);

use App\Documentation\Actions\ValidateDocumentation;
use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Documentation\Sources\LocalFilesystemDocumentationSource;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
| Everything here runs against `tests/fixtures/example`, a fictional package's
| `docs/` and `README.md` kept in this repository. Kept here on purpose: reading
| a sibling checkout would make these tests pass or fail according to a
| directory outside this repository, measuring a working tree instead of the
| contract.
*/

function fixtureRepository(): string
{
    return base_path('tests/fixtures/example');
}

function destination(): string
{
    return scratchRoot().'/docs-hub-retrieve/'.Str::random(12);
}

function mountedProject(?string $path = null, string $docsPath = 'docs'): Project
{
    $project = Project::factory()->create([
        'slug' => 'example',
        'docs_path' => $docsPath,
    ]);

    config()->set('documentation.local_sources', [
        'example' => $path ?? fixtureRepository(),
    ]);

    return $project;
}

function source(): LocalFilesystemDocumentationSource
{
    return new LocalFilesystemDocumentationSource;
}

afterEach(function (): void {
    File::deleteDirectory(scratchRoot().'/docs-hub-retrieve');
});

it('retrieves Example\'s documentation with no token and no network', function (): void {
    $project = mountedProject();
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    expect("{$destination}/docs/docs.yml")->toBeFile()
        ->and("{$destination}/docs/index.md")->toBeFile()
        ->and("{$destination}/docs/usage/authentication.md")->toBeFile()
        ->and("{$destination}/docs/architecture/theme-composition.md")->toBeFile()
        ->and("{$destination}/README.md")->toBeFile();
});

it('publishes what it retrieved without changing it', function (): void {
    $project = mountedProject();
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    // A package's tree must survive the trip through a source byte for byte, and still satisfy the contract at the
    // other end.
    expect(File::get("{$destination}/docs/docs.yml"))
        ->toBe(File::get(fixtureRepository().'/docs/docs.yml'))
        ->and((new ValidateDocumentation)->handle("{$destination}/docs")->issues)
        ->toBeEmpty();
});

it('copies the documentation and nothing else', function (): void {
    $project = mountedProject();
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    $retrieved = collect(File::allFiles($destination))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->sort()
        ->values();

    expect($retrieved)->toHaveCount(13)
        ->and($retrieved)->toContain('README.md')
        ->and($retrieved)->not->toContain('.pinned-at');
});

it('honours a project that keeps its documentation somewhere else', function (): void {
    $repository = scratchRoot().'/docs-hub-retrieve/elsewhere';
    File::ensureDirectoryExists("{$repository}/documentation");
    File::put("{$repository}/documentation/docs.yml", "navigation:\n  - index");
    File::put("{$repository}/documentation/index.md", "# Index\n");

    $project = mountedProject($repository, docsPath: 'documentation');
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    expect("{$destination}/documentation/docs.yml")->toBeFile();
});

it('retrieves a README-only repository', function (): void {
    $repository = scratchRoot().'/docs-hub-retrieve/readme-only';
    File::ensureDirectoryExists($repository);
    File::put("{$repository}/README.md", "# A small tool\n");

    $project = mountedProject($repository);
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    expect("{$destination}/README.md")->toBeFile()
        ->and("{$destination}/docs")->not->toBeDirectory();
});

it('identifies the documentation by its content', function (): void {
    $project = mountedProject();
    $version = ProjectVersion::factory()->for($project)->create();

    $commit = source()->resolveCommit($project, $version);

    expect($commit)->toHaveLength(64)
        ->and(source()->resolveCommit($project, $version))->toBe($commit);
});

it('changes identity when an uncommitted edit changes the documentation', function (): void {
    $repository = scratchRoot().'/docs-hub-retrieve/working-tree';
    File::ensureDirectoryExists("{$repository}/docs");
    File::put("{$repository}/docs/docs.yml", "navigation:\n  - index");
    File::put("{$repository}/docs/index.md", "# Index\n");

    $project = mountedProject($repository);
    $version = ProjectVersion::factory()->for($project)->create();

    $before = source()->resolveCommit($project, $version);

    // The whole reason this adapter exists: a change is previewable before it
    // is committed, so identity has to follow the working tree.
    File::put("{$repository}/docs/index.md", "# Index\n\nA new paragraph.\n");

    expect(source()->resolveCommit($project, $version))->not->toBe($before);
});

it('changes identity when a file is renamed but its contents are not', function (): void {
    $repository = scratchRoot().'/docs-hub-retrieve/renamed';
    File::ensureDirectoryExists("{$repository}/docs");
    File::put("{$repository}/docs/docs.yml", "navigation:\n  - index");
    File::put("{$repository}/docs/index.md", "# Index\n");

    $project = mountedProject($repository);
    $version = ProjectVersion::factory()->for($project)->create();

    $before = source()->resolveCommit($project, $version);

    File::move("{$repository}/docs/index.md", "{$repository}/docs/home.md");

    expect(source()->resolveCommit($project, $version))->not->toBe($before);
});

it('refuses a project with no local path configured', function (): void {
    $project = Project::factory()->create(['slug' => 'unmounted']);
    $version = ProjectVersion::factory()->for($project)->create();

    source()->retrieve($project, $version, destination());
})->throws(DocumentationSourceFailed::class, 'No local source path is configured');

it('refuses a local path that is not there', function (): void {
    $project = mountedProject(scratchRoot().'/docs-hub-retrieve/absent');
    $version = ProjectVersion::factory()->for($project)->create();

    source()->retrieve($project, $version, destination());
})->throws(DocumentationSourceFailed::class, 'which is not a directory');

it('copies nothing from a checkout with no documentation in it', function (): void {
    $repository = scratchRoot().'/docs-hub-retrieve/empty';
    File::ensureDirectoryExists("{$repository}/src");
    File::put("{$repository}/src/Example.php", '<?php');

    $project = mountedProject($repository);
    $version = ProjectVersion::factory()->for($project)->create();
    $destination = destination();

    source()->retrieve($project, $version, $destination);

    // Whether that is a problem is a question answered once by
    // the synchronizer rather than separately by every source.
    expect($destination)->not->toBeDirectory();
});
