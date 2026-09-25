<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| `docs:sync`. The command's job is selection and reporting; the
| operation itself is tested against the action.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
    File::deleteDirectory(storage_path('app/docs-staging'));
});

it('synchronizes every registered version by default', function (): void {
    $one = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $two = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect($one->refresh()->isPublished())->toBeTrue()
        ->and($two->refresh()->isPublished())->toBeTrue();
});

it('limits itself to one project when asked', function (): void {
    $example = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $toolkit = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    $this->artisan('docs:sync', ['project' => 'example'])->assertSuccessful();

    expect($example->refresh()->isPublished())->toBeTrue()
        ->and($toolkit->refresh()->isPublished())->toBeFalse();
});

it('limits itself to one version when asked', function (): void {
    $one = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $two = ProjectVersion::factory()->for($one->project)->create(['version' => '2.x']);

    $this->artisan('docs:sync', ['project' => 'example', 'version' => '2.x'])->assertSuccessful();

    expect($two->refresh()->isPublished())->toBeTrue()
        ->and($one->refresh()->isPublished())->toBeFalse();
});

it('reports a project that is not registered', function (): void {
    $this->artisan('docs:sync', ['project' => 'nonesuch'])
        ->expectsOutputToContain('No project is registered as `nonesuch`')
        ->assertFailed();
});

it('reports a version the project does not have', function (): void {
    Project::factory()->create(['slug' => 'example']);

    $this->artisan('docs:sync', ['project' => 'example', 'version' => '9.x'])
        ->expectsOutputToContain('has no version `9.x`')
        ->assertFailed();
});

it('succeeds at doing nothing when the registry is empty', function (): void {
    $this->artisan('docs:sync')
        ->expectsOutputToContain('No project versions matched')
        ->assertSuccessful();
});

it('reports an unchanged version rather than republishing it', function (): void {
    mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Example\n",
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->artisan('docs:sync')
        ->expectsOutputToContain('unchanged')
        ->assertSuccessful();
});

it('fails, and says which page, when a manifest is broken', function (): void {
    mounted([
        'docs/docs.yml' => "navigation:\n  - index\n  - instalation",
        'docs/index.md' => "# Example\n",
    ]);

    $this->artisan('docs:sync')
        ->expectsOutputToContain('did not pass validation')
        ->expectsOutputToContain('instalation.md')
        ->assertFailed();
});

it('keeps going when one version fails', function (): void {
    $broken = mounted([
        'docs/docs.yml' => "navigation:\n  - nowhere",
        'docs/index.md' => "# Example\n",
    ]);

    $fine = mounted([
        'docs/docs.yml' => "navigation:\n  - index",
        'docs/index.md' => "# Toolkit\n",
    ], slug: 'toolkit');

    config()->set('documentation.local_sources', [
        'example' => documentationScratch().'/example',
        'toolkit' => documentationScratch().'/toolkit',
    ]);

    // One broken manifest must not hold up every other project's
    // documentation, so the command reports and continues.
    $this->artisan('docs:sync')->assertFailed();

    expect($broken->refresh()->isPublished())->toBeFalse()
        ->and($fine->refresh()->isPublished())->toBeTrue();
});

it('publishes Example from its pinned checkout', function (): void {
    $project = Project::factory()->create(['slug' => 'example']);
    $version = ProjectVersion::factory()->for($project)->default()->create(['version' => '1.x']);

    config()->set('documentation.local_sources', [
        'example' => base_path('tests/fixtures/example'),
    ]);

    $this->artisan('docs:sync', ['project' => 'example'])
        ->expectsOutputToContain('structured')
        ->assertSuccessful();

    expect($version->refresh()->pages()->count())->toBe(11);
});
