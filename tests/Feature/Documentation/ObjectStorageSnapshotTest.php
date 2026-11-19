<?php

declare(strict_types=1);

use App\Documentation\Actions\PublishSnapshot;
use App\Documentation\Storage\SnapshotStore;
use App\Enums\DocumentationType;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| The snapshot store against a real S3-compatible disk rather than a fake.
|
| Object storage has no directories, no rename and no directory timestamps, and
| a local fake has all three, so only a real bucket shows whether the store
| relies on any of them. Runs when the `s3` disk names a bucket and an endpoint
| (Herd's MinIO locally) and is skipped otherwise. Each test works under its own
| project prefix and removes it afterwards.
*/

function objectStorageConfigured(): bool
{
    return filled(config('filesystems.disks.s3.bucket')) && filled(config('filesystems.disks.s3.endpoint'));
}

beforeEach(function (): void {
    if (! objectStorageConfigured()) {
        $this->markTestSkipped('No S3-compatible endpoint is configured for the s3 disk.');
    }

    config()->set('documentation.snapshots.disk', 's3');

    $this->store = new SnapshotStore;

    $this->slug = 'test-'.Str::lower(Str::random(12));
    $this->version = ProjectVersion::factory()
        ->for(Project::factory()->create(['slug' => $this->slug]))
        ->default()
        ->create(['version' => '1.x']);
});

afterEach(function (): void {
    if (objectStorageConfigured()) {
        Storage::disk('s3')->deleteDirectory($this->slug);
        Storage::disk('s3')->deleteDirectory("{$this->slug}-page");
    }

    File::deleteDirectory(documentationScratch());
});

/** @param array<string, string> $files */
function objectStorageTree(array $files): string
{
    $root = documentationScratch().'/'.Str::random(12);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    return $root;
}

it('writes and reads a snapshot in the bucket', function (): void {
    $this->store->write($this->version, 'aaaaaaa', objectStorageTree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Index\n",
    ]));

    expect($this->store->exists($this->version, 'aaaaaaa'))->toBeTrue()
        ->and($this->store->exists($this->version, 'bbbbbbb'))->toBeFalse()
        ->and($this->store->read($this->version, 'aaaaaaa', 'docs/index.md'))->toBe("# Index\n")
        ->and($this->store->read($this->version, 'aaaaaaa', 'docs/missing.md'))->toBeNull()
        ->and($this->store->documentationType($this->version, 'aaaaaaa'))->toBe(DocumentationType::Structured);
});

it('lists and deletes snapshots by prefix', function (): void {
    foreach (['aaaaaaa', 'bbbbbbb'] as $commit) {
        $this->store->write($this->version, $commit, objectStorageTree(['docs/index.md' => "# {$commit}\n"]));
    }

    expect($this->store->snapshots($this->version))->toEqualCanonicalizing(['aaaaaaa', 'bbbbbbb']);

    $this->store->delete($this->version, 'aaaaaaa');

    expect($this->store->snapshots($this->version))->toBe(['bbbbbbb']);
});

it('prunes the oldest superseded snapshot without directory timestamps', function (): void {
    // Written a second apart because that is the resolution S3 reports a
    // modification time at, and the order is what prune is judged on.
    foreach (['zzzzzzz', 'aaaaaaa', 'mmmmmmm'] as $index => $commit) {
        if ($index > 0) {
            sleep(1);
        }

        $this->store->write($this->version, $commit, objectStorageTree(['docs/index.md' => "# {$commit}\n"]));
    }

    expect($this->store->prune($this->version, 'mmmmmmm', 2))->toBe(['zzzzzzz'])
        ->and($this->store->snapshots($this->version))->toEqualCanonicalizing(['aaaaaaa', 'mmmmmmm']);
});

it('serves a published page out of the bucket', function (): void {
    publishDocumentation(['index' => "# Index\n\nFrom the bucket.\n"], slug: "{$this->slug}-page");

    $this->get("/{$this->slug}-page/1.x")
        ->assertSuccessful()
        ->assertSee('From the bucket.');
});

it('streams a published asset out of the bucket', function (): void {
    $this->store->write($this->version, 'aaaaaaa', objectStorageTree([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Index\n",
        'docs/assets/diagram.png' => 'PNGDATA',
    ]));

    (new PublishSnapshot)->handle($this->version, 'aaaaaaa');

    $asset = $this->get("/assets/{$this->slug}/1.x/diagram.png")->assertSuccessful();

    expect($asset->streamedContent())->toBe('PNGDATA')
        ->and($asset->headers->get('Content-Type'))->toBe('image/png');
});
