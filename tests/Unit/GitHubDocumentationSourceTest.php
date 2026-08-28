<?php

declare(strict_types=1);

use App\Documentation\Data\ResolvedGitHubReference;
use App\Documentation\Exceptions\GitHubDocumentationSourceException;
use App\Documentation\Sources\GitHubDocumentationSource;
use App\Http\Integrations\GitHub\GitHubConnector;
use App\Http\Integrations\GitHub\Requests\DownloadRepositoryArchive;
use App\Http\Integrations\GitHub\Requests\GetGitReference;
use App\Http\Integrations\GitHub\Requests\GetGitTag;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('resolves a GitHub reference to an immutable commit', function (): void {
    $mockClient = new MockClient([
        GetGitReference::class => MockResponse::make([
            'object' => ['type' => 'commit', 'sha' => str_repeat('a', 40)],
        ]),
    ]);
    $connector = (new GitHubConnector)->withMockClient($mockClient);

    $result = new GitHubDocumentationSource($connector)->resolve('awcodes/docs', 'heads/main');

    expect($result)->toEqual(new ResolvedGitHubReference('awcodes/docs', 'heads/main', str_repeat('a', 40)));
    $mockClient->assertSent(GetGitReference::class);
});

it('dereferences annotated tags to a commit', function (): void {
    $mockClient = new MockClient([
        GetGitReference::class => MockResponse::make([
            'object' => ['type' => 'tag', 'sha' => str_repeat('b', 40)],
        ]),
        GetGitTag::class => MockResponse::make([
            'object' => ['type' => 'commit', 'sha' => str_repeat('c', 40)],
        ]),
    ]);
    $connector = (new GitHubConnector)->withMockClient($mockClient);

    $result = new GitHubDocumentationSource($connector)->resolve('awcodes/docs', 'tags/v1.0.0');

    expect($result->commitSha)->toBe(str_repeat('c', 40));
    $mockClient->assertSentInOrder([GetGitReference::class, GetGitTag::class]);
});

it('rejects invalid repositories before sending a request', function (): void {
    $mockClient = new MockClient;
    $connector = (new GitHubConnector)->withMockClient($mockClient);

    expect(fn (): ResolvedGitHubReference => new GitHubDocumentationSource($connector)->resolve('../docs', 'heads/main'))
        ->toThrow(GitHubDocumentationSourceException::class, 'Invalid GitHub repository');

    $mockClient->assertSentCount(0);
});

it('reports failed GitHub lookups without leaking credentials', function (): void {
    $mockClient = new MockClient([
        GetGitReference::class => MockResponse::make(['message' => 'Not Found'], 404),
    ]);
    $connector = (new GitHubConnector)->withMockClient($mockClient);

    expect(fn (): ResolvedGitHubReference => new GitHubDocumentationSource($connector)->resolve('awcodes/docs', 'heads/missing'))
        ->toThrow(GitHubDocumentationSourceException::class, 'status [404]');
});

it('downloads an archive pinned to the resolved commit', function (): void {
    $mockClient = new MockClient([
        DownloadRepositoryArchive::class => MockResponse::make('archive contents'),
    ]);
    $connector = (new GitHubConnector)->withMockClient($mockClient);
    $destination = tempnam(sys_get_temp_dir(), 'docs-archive-');

    expect($destination)->not->toBeFalse();

    try {
        new GitHubDocumentationSource($connector)->download(
            new ResolvedGitHubReference('awcodes/docs', 'heads/main', str_repeat('d', 40)),
            $destination,
        );

        expect(file_get_contents($destination))->toBe('archive contents');
        $mockClient->assertSent(DownloadRepositoryArchive::class);
    } finally {
        if (is_string($destination) && is_file($destination)) {
            unlink($destination);
        }
    }
});
