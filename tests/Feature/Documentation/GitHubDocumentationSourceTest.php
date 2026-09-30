<?php

declare(strict_types=1);

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Documentation\Sources\GitHubDocumentationSource;
use App\Http\Integrations\GitHub\Requests\DownloadRawFile;
use App\Http\Integrations\GitHub\Requests\ReadTree;
use App\Http\Integrations\GitHub\Requests\ResolveRef;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

/*
| Files come from the tree and raw downloads rather than the zipball, because a
| zipball honours `export-ignore` and packages mark `/docs` that way. Most of
| this file is about that, and about keeping the token off a host that does not
| need it.
*/

const COMMIT = 'f7c193d2a1b4c5d6e7f8091a2b3c4d5e6f708192';

beforeEach(function (): void {
    config()->set('documentation.github.token', 'ci-user-token');
});

afterEach(function (): void {
    File::deleteDirectory(scratchRoot().'/docs-hub-github');
});

function githubProject(string $docsPath = 'docs'): Project
{
    return Project::factory()->create([
        'slug' => 'example',
        'repository' => 'acme/example',
        'docs_path' => $docsPath,
    ]);
}

/**
 * Fake a repository at `COMMIT`: its tree, and each file's raw contents.
 *
 * @param  array<string, string>  $files
 * @param  array<string, mixed>  $tree  Overrides for the tree response.
 */
function fakeRepository(array $files, array $tree = []): void
{
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        ReadTree::class => MockResponse::make([
            'sha' => COMMIT,
            'tree' => array_map(
                fn (string $path): array => ['path' => $path, 'mode' => '100644', 'type' => 'blob'],
                array_keys($files),
            ),
            'truncated' => false,
            ...$tree,
        ], 200),
        DownloadRawFile::class => function (PendingRequest $request) use ($files): MockResponse {
            $path = rawurldecode(Str::after($request->getUrl(), '/acme/example/'.COMMIT.'/'));

            return array_key_exists($path, $files)
                ? MockResponse::make($files[$path], 200)
                : MockResponse::make('404: Not Found', 404);
        },
    ]);
}

/** @return list<string> */
function retrievedFiles(string $destination): array
{
    return collect(File::allFiles($destination))
        ->map(fn ($file): string => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();
}

function destinationDirectory(): string
{
    return scratchRoot().'/docs-hub-github/out-'.Str::random(8);
}

it('resolves a ref to the commit it names', function (): void {
    Saloon::fake([ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200)]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    expect((new GitHubDocumentationSource)->resolveCommit($project, $version))->toBe(COMMIT);

    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof ResolveRef
        && str_contains($response->getPendingRequest()->getUrl(), '/repos/acme/example/commits/1.x'));
});

it('authenticates every API request when a token is configured', function (): void {
    Saloon::fake([ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200)]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->resolveCommit($project, $version);

    // Optional for public repositories, but it lifts the API rate limit, so
    // it has to be sent when it is configured.
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof ResolveRef
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer ci-user-token');
});

it('is the source for an unmounted project, token or not', function (): void {
    config()->set('documentation.github.token');

    expect((new App\Documentation\Sources\DocumentationSourceResolver)->for(githubProject()))
        ->toBeInstanceOf(GitHubDocumentationSource::class);
});

it('reads a public repository without a token', function (): void {
    config()->set('documentation.github.token');

    Saloon::fake([ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200)]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    expect((new GitHubDocumentationSource)->resolveCommit($project, $version))->toBe(COMMIT);

    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof ResolveRef
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('lists the tree of the resolved commit, not the ref', function (): void {
    fakeRepository(['README.md' => "# Example\n"]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());

    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof ReadTree
        && str_contains($response->getPendingRequest()->getUrl(), '/repos/acme/example/git/trees/'.COMMIT)
        && $response->getPendingRequest()->query()->get('recursive') === '1');
});

it('downloads the documentation and nothing else', function (): void {
    fakeRepository([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
        'docs/usage/authentication.md' => "# Authentication\n",
        'README.md' => "# acme/example\n",
        'src/ExampleServiceProvider.php' => '<?php',
        'composer.json' => '{}',
        '.gitattributes' => "/docs export-ignore\n",
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    expect(retrievedFiles($destination))->toBe([
        'README.md',
        'docs/docs.yml',
        'docs/index.md',
        'docs/usage/authentication.md',
    ])->and(File::get("{$destination}/docs/usage/authentication.md"))->toBe("# Authentication\n");

    Saloon::assertNotSent(fn (Request $request, Response $response): bool => $request instanceof DownloadRawFile
        && str_contains($response->getPendingRequest()->getUrl(), 'composer.json'));
});

it('downloads each file at the commit from raw content, without the credential', function (): void {
    fakeRepository(['docs/index.md' => "# Example\n"]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());

    // The API token has no business on another host, and raw downloads of a
    // public repository do not need it.
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof DownloadRawFile
        && $response->getPendingRequest()->getUrl() === 'https://raw.githubusercontent.com/acme/example/'.COMMIT.'/docs/index.md'
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('skips directories, submodules and symlinks in the tree', function (): void {
    fakeRepository(['docs/index.md' => "# Example\n"], tree: [
        'tree' => [
            ['path' => 'docs', 'mode' => '040000', 'type' => 'tree'],
            ['path' => 'docs/index.md', 'mode' => '100644', 'type' => 'blob'],
            ['path' => 'docs/vendored', 'mode' => '160000', 'type' => 'commit'],
            ['path' => 'docs/linked.md', 'mode' => '120000', 'type' => 'blob'],
        ],
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    expect(retrievedFiles($destination))->toBe(['docs/index.md']);
});

it('honours a project that keeps documentation somewhere else', function (): void {
    fakeRepository([
        'documentation/docs.yml' => "navigation:\n  - index",
        'docs/decoy.md' => '# Not this one',
    ]);

    $project = githubProject(docsPath: 'documentation');
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    expect("{$destination}/documentation/docs.yml")->toBeFile()
        ->and("{$destination}/docs")->not->toBeDirectory();
});

it('refuses a path that would escape the destination', function (): void {
    fakeRepository([
        'docs/index.md' => "# Example\n",
        'docs/../../../escaped.md' => 'nope',
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    // Git will not record `..` as a segment, but this writes files from a
    // remote answer.
    expect(File::allFiles($destination))->toHaveCount(1)
        ->and(dirname($destination).'/escaped.md')->not->toBeFile();
});

/*
| Failing the way a private repository fails.
*/

it('reports a ref that does not resolve', function (): void {
    Saloon::fake([ResolveRef::class => MockResponse::make(['message' => 'Not Found'], 404)]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '9.x']);

    (new GitHubDocumentationSource)->resolveCommit($project, $version);
})->throws(DocumentationSourceFailed::class, 'has no ref `9.x`');

it('suggests a private or renamed repository when a 404 might be one', function (): void {
    Saloon::fake([ResolveRef::class => MockResponse::make(['message' => 'Not Found'], 404)]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    // GitHub answers a repository it will not show exactly as it answers one
    // that does not exist. That reads like a missing ref, so the message says
    // both rather than making somebody rediscover it.
    expect(fn (): mixed => (new GitHubDocumentationSource)->resolveCommit($project, $version))
        ->toThrow(DocumentationSourceFailed::class, 'private or has been renamed');
});

it('refuses a truncated tree rather than publishing part of it', function (): void {
    fakeRepository(['docs/index.md' => "# Example\n"], tree: ['truncated' => true]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());
})->throws(DocumentationSourceFailed::class, 'too large to list');

it('reports a tree that cannot be listed', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        ReadTree::class => MockResponse::make(['message' => 'Not Found'], 404),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());
})->throws(DocumentationSourceFailed::class, 'could not be listed');

it('reports a file that will not download', function (): void {
    fakeRepository(['docs/index.md' => "# Example\n"], tree: [
        'tree' => [['path' => 'docs/missing.md', 'mode' => '100644', 'type' => 'blob']],
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());
})->throws(DocumentationSourceFailed::class, '`docs/missing.md` in `acme/example` at `1.x` could not be downloaded');

it('costs two API requests to synchronize a version', function (): void {
    fakeRepository([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    $source = new GitHubDocumentationSource;

    // What synchronization does: ask for the commit, then ask for the files.
    // The interface hands the second call a ref rather than the commit already
    // resolved, so without memoization the ref is resolved twice. The raw
    // downloads are not API requests and do not count against its limit.
    $source->resolveCommit($project, $version);
    $source->retrieve($project, $version, destinationDirectory());

    Saloon::mockClient()->assertSentCount(1, ResolveRef::class);
    Saloon::mockClient()->assertSentCount(1, ReadTree::class);
    Saloon::mockClient()->assertSentCount(2, DownloadRawFile::class);
});
