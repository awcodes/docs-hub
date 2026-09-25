<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
| The command's contract is its exit code: repository CI reads that and nothing
| else. Warnings must not fail a build, or the strict-publication bargain turns
| into noise authors learn to ignore.
*/

afterEach(function (): void {
    File::deleteDirectory(scratchRoot().'/docs-hub-command');
});

function commandRoot(array $files): string
{
    $root = scratchRoot().'/docs-hub-command';

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    return $root;
}

it('passes Example\'s documentation from a plain checkout', function (): void {
    $this->artisan('docs:validate', ['path' => base_path('tests/fixtures/example/docs')])
        ->assertSuccessful();
});

it('fails on a broken navigation reference', function (): void {
    $root = commandRoot([
        'docs.yml' => "navigation:\n  - index\n  - instalation",
        'index.md' => "# Index\n",
    ]);

    $this->artisan('docs:validate', ['path' => $root])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('passes with warnings rather than failing the build', function (): void {
    $root = commandRoot([
        'docs.yml' => "navigation:\n  - index\n  - index",
        'index.md' => "# Index\n",
    ]);

    $this->artisan('docs:validate', ['path' => $root])
        ->expectsOutputToContain('more than once')
        ->assertSuccessful();
});

it('fails when there is nothing at the path', function (): void {
    $this->artisan('docs:validate', ['path' => scratchRoot().'/docs-hub-nowhere'])
        ->assertFailed();
});
