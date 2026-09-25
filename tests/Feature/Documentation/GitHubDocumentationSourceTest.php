<?php

declare(strict_types=1);

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Documentation\Sources\GitHubDocumentationSource;
use App\Http\Integrations\GitHub\Requests\DownloadArchive;
use App\Http\Integrations\GitHub\Requests\DownloadSignedArchive;
use App\Http\Integrations\GitHub\Requests\ResolveRef;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

/*
| The credential trap is what most of this file is for. GitHub redirects an
| archive download to `codeload.github.com`, HTTP clients drop `Authorization`
| across hosts, and against a *public* repository the redirected request
| succeeds anyway — so the bug is invisible until it is a 404 on a private one
| that reads like a missing ref.
*/

const COMMIT = 'f7c193d2a1b4c5d6e7f8091a2b3c4d5e6f708192';

beforeEach(function (): void {
    config()->set('documentation.github.token', 'ci-user-token');
});

afterEach(function (): void {
    File::deleteDirectory(scratchRoot().'/docs-hub-github');
    File::deleteDirectory(storage_path('app/docs-archives'));
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
 * A zipball shaped the way GitHub builds one: everything under a generated
 * top-level directory, and most of it nothing to do with documentation.
 *
 * @param  array<string, string>  $files
 */
function zipball(array $files, string $prefix = 'acme-example-f7c193d'): string
{
    $path = scratchRoot().'/docs-hub-github/'.Str::random(12).'.zip';

    File::ensureDirectoryExists(dirname($path));

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($files as $name => $contents) {
        $zip->addFromString("{$prefix}/{$name}", $contents);
    }

    $zip->close();

    return (string) file_get_contents($path);
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

it('does not send the credential to the signed archive URL', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make('', 302, [
            'Location' => 'https://codeload.github.com/acme/example/legacy.zip/'.COMMIT.'?token=short-lived',
        ]),
        DownloadSignedArchive::class => MockResponse::make(zipball([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n",
        ]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());

    // The signed URL carries its own short-lived authorization in the query
    // string. Sending a long-lived token to a host that did not
    // ask for it would be the opposite of the fix.
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof DownloadSignedArchive
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('reads the redirect rather than following it', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make('', 302, [
            'Location' => 'https://codeload.github.com/acme/example/legacy.zip/'.COMMIT,
        ]),
        DownloadSignedArchive::class => MockResponse::make(zipball(['README.md' => "# Example\n"]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());

    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof DownloadArchive
        && $response->getPendingRequest()->config()->get('allow_redirects') === false);
});

it('unpacks the documentation and nothing else', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make('', 302, [
            'Location' => 'https://codeload.github.com/acme/example/legacy.zip/'.COMMIT,
        ]),
        DownloadSignedArchive::class => MockResponse::make(zipball([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n",
            'docs/usage/authentication.md' => "# Authentication\n",
            'README.md' => "# acme/example\n",
            'src/ExampleServiceProvider.php' => '<?php',
            'composer.json' => '{}',
            'vendor/autoload.php' => '<?php',
        ]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    $retrieved = collect(File::allFiles($destination))
        ->map(fn ($file): string => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();

    expect($retrieved)->toBe([
        'README.md',
        'docs/docs.yml',
        'docs/index.md',
        'docs/usage/authentication.md',
    ]);
});

it('honours a project that keeps documentation somewhere else', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make(zipball([
            'documentation/docs.yml' => "navigation:\n  - index",
            'docs/decoy.md' => '# Not this one',
        ]), 200),
    ]);

    $project = githubProject(docsPath: 'documentation');
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    expect("{$destination}/documentation/docs.yml")->toBeFile()
        ->and("{$destination}/docs")->not->toBeDirectory();
});

it('refuses an archive entry that would escape the destination', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make(zipball([
            'docs/index.md' => "# Example\n",
            'docs/../../../escaped.md' => 'nope',
        ]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);
    $destination = destinationDirectory();

    (new GitHubDocumentationSource)->retrieve($project, $version, $destination);

    // A repository cannot put `..` in a path through git, but an archive is
    // just bytes and this writes files.
    expect(File::allFiles($destination))->toHaveCount(1)
        ->and(dirname($destination).'/escaped.md')->not->toBeFile();
});

it('leaves no downloaded archive behind', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make(zipball(['README.md' => "# Example\n"]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());

    expect(File::glob(storage_path('app/docs-archives/*.zip')))->toBeEmpty();
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

it('reports an archive that will not download', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        // What a dropped credential looks like from the outside: not a 401,
        // just a repository that appears not to exist.
        DownloadArchive::class => MockResponse::make(['message' => 'Not Found'], 404),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());
})->throws(DocumentationSourceFailed::class, 'could not be downloaded');

it('reports a redirect that points nowhere', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make('', 302),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    (new GitHubDocumentationSource)->retrieve($project, $version, destinationDirectory());
})->throws(DocumentationSourceFailed::class, 'could not be downloaded');

it('costs two requests to synchronize a version', function (): void {
    Saloon::fake([
        ResolveRef::class => MockResponse::make(['sha' => COMMIT], 200),
        DownloadArchive::class => MockResponse::make(zipball([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# Example\n",
        ]), 200),
    ]);

    $project = githubProject();
    $version = ProjectVersion::factory()->for($project)->create(['git_ref' => '1.x']);

    $source = new GitHubDocumentationSource;

    // What synchronization does: ask for the commit, then ask for the archive.
    // One request each is enough, and the interface hands the
    // second call a ref rather than the commit already resolved — so without
    // memoization this quietly becomes three.
    $source->resolveCommit($project, $version);
    $source->retrieve($project, $version, destinationDirectory());

    Saloon::assertSentCount(2);
});
