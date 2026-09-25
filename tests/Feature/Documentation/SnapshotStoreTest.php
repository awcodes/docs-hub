<?php

declare(strict_types=1);

use App\Documentation\Actions\PublishSnapshot;
use App\Documentation\Exceptions\SnapshotFailed;
use App\Documentation\Sources\LocalFilesystemDocumentationSource;
use App\Documentation\Storage\SnapshotStore;
use App\Enums\DocumentationType;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Publication is a column update, so most of what is worth asserting here is
| what does *not* happen: no rename, no move, and no way for a failed write to
| become the version a reader sees.
*/

beforeEach(function (): void {
    Storage::fake('documentation');

    $this->store = new SnapshotStore;

    $this->project = Project::factory()->create(['slug' => 'example']);
    $this->version = ProjectVersion::factory()->for($this->project)->create(['version' => '1.x']);
});

afterEach(function (): void {
    File::deleteDirectory(scratchRoot().'/docs-hub-snapshots');
});

/** @param array<string, string> $files */
function tree(array $files): string
{
    $root = scratchRoot().'/docs-hub-snapshots/'.Str::random(12);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    return $root;
}

function exampleTree(Project $project, ProjectVersion $version): string
{
    $root = scratchRoot().'/docs-hub-snapshots/'.Str::random(12);

    (new LocalFilesystemDocumentationSource)->retrieve($project, $version, $root);

    return $root;
}

it('addresses a snapshot by project, version and commit', function (): void {
    $this->store->write($this->version, 'f7c193d', tree(['docs/index.md' => "# Index\n"]));

    Storage::disk('documentation')->assertExists('example/1.x/f7c193d/docs/index.md');
});

it('takes a whole retrieved tree without altering it', function (): void {
    config()->set('documentation.local_sources', [
        'example' => base_path('tests/fixtures/example'),
    ]);

    $this->store->write($this->version, 'abc1234', exampleTree($this->project, $this->version));

    expect($this->store->read($this->version, 'abc1234', 'docs/docs.yml'))
        ->toBe(File::get(base_path('tests/fixtures/example/docs/docs.yml')))
        ->and($this->store->read($this->version, 'abc1234', 'docs/usage/authentication.md'))
        ->toBe(File::get(base_path('tests/fixtures/example/docs/usage/authentication.md')));
});

it('keeps two commits of the same version side by side', function (): void {
    $this->store->write($this->version, 'aaaaaaa', tree(['docs/index.md' => "# Old\n"]));
    $this->store->write($this->version, 'bbbbbbb', tree(['docs/index.md' => "# New\n"]));

    expect($this->store->read($this->version, 'aaaaaaa', 'docs/index.md'))->toBe("# Old\n")
        ->and($this->store->read($this->version, 'bbbbbbb', 'docs/index.md'))->toBe("# New\n")
        ->and($this->store->snapshots($this->version))->toHaveCount(2);
});

it('reads nothing for a page that is not in the snapshot', function (): void {
    $this->store->write($this->version, 'aaaaaaa', tree(['docs/index.md' => "# Index\n"]));

    expect($this->store->read($this->version, 'aaaaaaa', 'docs/nowhere.md'))->toBeNull();
});

it('refuses a path that tries to climb out of a snapshot', function (): void {
    $this->store->write($this->version, 'aaaaaaa', tree(['docs/index.md' => "# Index\n"]));

    $this->store->read($this->version, 'aaaaaaa', '../../../.env');
})->throws(SnapshotFailed::class, 'is not a path inside a snapshot');

it('refuses a version string that would escape its directory', function (): void {
    $version = ProjectVersion::factory()->for($this->project)->create(['version' => '../evil']);

    $this->store->path($version, 'aaaaaaa');
})->throws(SnapshotFailed::class, 'cannot be part of a snapshot path');

it('refuses to snapshot a directory that is not there', function (): void {
    $this->store->write($this->version, 'aaaaaaa', scratchRoot().'/docs-hub-snapshots/absent');
})->throws(SnapshotFailed::class, 'nothing to snapshot');

it('reads the documentation type off the snapshot', function (): void {
    $this->store->write($this->version, 'structured', tree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Index\n",
        'README.md' => "# Example\n",
    ]));

    $this->store->write($this->version, 'readmeonly', tree(['README.md' => "# A small tool\n"]));

    $this->store->write($this->version, 'nothingyet', tree(['docs/index.md' => "# Orphan\n"]));

    expect($this->store->documentationType($this->version, 'structured'))
        ->toBe(DocumentationType::Structured)
        ->and($this->store->documentationType($this->version, 'readmeonly'))
        ->toBe(DocumentationType::Readme)
        ->and($this->store->documentationType($this->version, 'nothingyet'))
        ->toBe(DocumentationType::None);
});

it('publishes by moving a pointer and nothing else', function (): void {
    $this->store->write($this->version, 'aaaaaaa', tree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Index\n",
    ]));

    (new PublishSnapshot)->handle($this->version, 'aaaaaaa');

    expect($this->version->refresh()->active_snapshot)->toBe('aaaaaaa')
        ->and($this->version->source_commit)->toBe('aaaaaaa')
        ->and($this->version->documentation_type)->toBe(DocumentationType::Structured)
        ->and($this->version->last_synced_at)->not->toBeNull()
        ->and($this->version->isPublished())->toBeTrue();

    // The snapshot is still exactly where it was written.
    Storage::disk('documentation')->assertExists('example/1.x/aaaaaaa/docs/index.md');
});

it('keeps serving the old snapshot until the new one is published', function (): void {
    $this->store->write($this->version, 'aaaaaaa', tree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Old\n",
    ]));
    (new PublishSnapshot)->handle($this->version, 'aaaaaaa');

    // A second sync writes, and then fails before it can publish.
    $this->store->write($this->version, 'bbbbbbb', tree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# New\n",
    ]));

    expect($this->store->readPublished($this->version->refresh(), 'docs/index.md'))->toBe("# Old\n");
});

it('rolls back to a retained snapshot without resynchronizing', function (): void {
    foreach (['aaaaaaa' => "# One\n", 'bbbbbbb' => "# Two\n"] as $commit => $body) {
        $this->store->write($this->version, $commit, tree([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => $body,
        ]));

        (new PublishSnapshot)->handle($this->version, $commit);
    }

    (new PublishSnapshot)->handle($this->version, 'aaaaaaa');

    expect($this->store->readPublished($this->version->refresh(), 'docs/index.md'))->toBe("# One\n");
});

it('refuses to publish a snapshot that was never written', function (): void {
    (new PublishSnapshot)->handle($this->version, 'nevermind');
})->throws(SnapshotFailed::class, 'never written');

it('reads nothing published for a version that has never synchronized', function (): void {
    expect($this->store->readPublished($this->version, 'docs/index.md'))->toBeNull();
});

it('prunes the oldest superseded snapshots, not the alphabetically last', function (): void {
    config()->set('documentation.snapshots.retain', 2);

    // Ages set explicitly and deliberately out of alphabetical order: a commit
    // hash sorts alphabetically, and that has nothing to do with when it was
    // written. `zzzzzzz` is the oldest and must be the one to go.
    $ages = ['zzzzzzz' => 3000, 'aaaaaaa' => 2000, 'mmmmmmm' => 1000];

    foreach ($ages as $commit => $secondsAgo) {
        $this->store->write($this->version, $commit, tree([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# {$commit}\n",
        ]));

        touch(
            Storage::disk('documentation')->path("example/1.x/{$commit}"),
            now()->subSeconds($secondsAgo)->getTimestamp(),
        );
    }

    $removed = $this->store->prune($this->version, 'mmmmmmm', 2);

    expect($removed)->toBe(['zzzzzzz'])
        ->and($this->store->snapshots($this->version))->toHaveCount(2)
        ->and($this->store->snapshots($this->version))->toContain('mmmmmmm', 'aaaaaaa');
});

it('prunes on publication so a rollback target survives', function (): void {
    config()->set('documentation.snapshots.retain', 2);

    foreach (['aaaaaaa', 'bbbbbbb', 'ccccccc'] as $commit) {
        $this->store->write($this->version, $commit, tree([
            'docs/docs.yml' => "navigation:\n  - index",
            'docs/index.md' => "# {$commit}\n",
        ]));

        (new PublishSnapshot)->handle($this->version, $commit);
    }

    expect($this->store->snapshots($this->version))->toHaveCount(2)
        ->and($this->store->snapshots($this->version))->toContain('ccccccc');
});

it('never prunes the snapshot it was told to keep', function (): void {
    config()->set('documentation.snapshots.retain', 1);

    $this->store->write($this->version, 'aaaaaaa', tree(['docs/index.md' => "# One\n"]));
    $this->store->write($this->version, 'bbbbbbb', tree(['docs/index.md' => "# Two\n"]));

    $this->store->prune($this->version, 'aaaaaaa', 1);

    expect($this->store->snapshots($this->version))->toBe(['aaaaaaa']);
});

it('keeps one version\'s snapshots out of another\'s', function (): void {
    $other = ProjectVersion::factory()->for($this->project)->create(['version' => '2.x']);

    $this->store->write($this->version, 'aaaaaaa', tree(['docs/index.md' => "# One\n"]));
    $this->store->write($other, 'aaaaaaa', tree(['docs/index.md' => "# Two\n"]));

    expect($this->store->read($this->version, 'aaaaaaa', 'docs/index.md'))->toBe("# One\n")
        ->and($this->store->read($other, 'aaaaaaa', 'docs/index.md'))->toBe("# Two\n");
});
