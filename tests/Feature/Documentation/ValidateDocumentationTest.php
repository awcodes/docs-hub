<?php

declare(strict_types=1);

use App\Documentation\Actions\ValidateDocumentation;
use App\Documentation\Data\ValidationIssue;
use App\Documentation\Data\ValidationResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
| Validation is what makes strict publication fair, so these tests are mostly
| about the difference between an error and a warning: an error stops a version
| republishing, a warning is advice.
*/

/** Every fixture tree lives under one directory, so cleanup needs no bookkeeping. */
function fixturesRoot(): string
{
    return scratchRoot().'/docs-hub-fixtures';
}

/** @param array<string, string> $files */
function docsRoot(array $files): string
{
    $root = fixturesRoot().'/'.Str::random(12);

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$root}/{$path}"));
        File::put("{$root}/{$path}", $contents);
    }

    return $root;
}

/** @param array<string, string>|string $files */
function validate(array|string $files): ValidationResult
{
    return (new ValidateDocumentation)->handle(
        is_string($files) ? $files : docsRoot($files),
    );
}

function messages(ValidationResult $result, string $severity = 'errors'): string
{
    /** @var list<ValidationIssue> $issues */
    $issues = $result->{$severity}();

    return implode(' | ', array_map(
        static fn (ValidationIssue $issue): string => $issue->message,
        $issues,
    ));
}

afterEach(function (): void {
    File::deleteDirectory(fixturesRoot());
});

it('passes the documentation this hub exists to publish', function (): void {
    $result = validate(base_path('tests/fixtures/example/docs'));

    expect($result->passed())->toBeTrue()
        ->and($result->issues)->toBeEmpty();
});

it('reports a documentation root that is not there', function (): void {
    $result = validate(scratchRoot().'/docs-hub-absent');

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('no documentation root');
});

it('requires a docs.yml inside the root', function (): void {
    $result = validate(['index.md' => "# Index\n"]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('must contain `docs.yml`');
});

it('says only that the manifest is broken when the manifest is broken', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - label: Usage",
        'index.md' => "# Index\n",
    ]);

    expect($result->errors())->toHaveCount(1)
        ->and(messages($result))->toContain('has no `children` list');
});

it('fails a navigation entry pointing at a file that does not exist', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\n  - instalation",
        'index.md' => "# Index\n",
    ]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('`instalation.md` does not exist');
});

it('leaves an unlisted page alone', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index",
        'index.md' => "# Index\n",
        'half-written.md' => "# Not in the sidebar yet\n",
    ]);

    expect($result->issues)->toBeEmpty();
});

it('warns when navigation lists the same page twice', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\n  - index",
        'index.md' => "# Index\n",
    ]);

    expect($result->passed())->toBeTrue()
        ->and(messages($result, 'warnings'))->toContain('more than once');
});

it('fails two pages that resolve to one URL', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index",
        'index.md' => "# Index\n",
        'setup.md' => "---\nslug: index\n---\n\n# Setup\n",
    ]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('claimed by more than one page');
});

it('allows the same final segment in different directories', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - usage/overview\n  - architecture/overview",
        'usage/overview.md' => "# Usage\n",
        'architecture/overview.md' => "# Architecture\n",
    ]);

    expect($result->issues)->toBeEmpty();
});

it('keeps a slug in its own directory', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - usage/authentication",
        'usage/authentication.md' => "---\nslug: sso\n---\n\n# SSO\n",
        'sso.md' => "# Something else entirely\n",
    ]);

    expect($result->issues)->toBeEmpty();
});

it('warns about a top-level page that looks like a version', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\n  - upgrading",
        'index.md' => "# Index\n",
        'upgrading.md' => "---\nslug: 2.x\n---\n\n# Upgrading\n",
    ]);

    expect($result->passed())->toBeTrue()
        ->and(messages($result, 'warnings'))->toContain('looks like a version string');
});

it('fails a redirect pointing at nothing', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\nredirects:\n  getting-started: instalation",
        'index.md' => "# Index\n",
    ]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('which does not exist');
});

it('fails a redirect pointing at itself', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\nredirects:\n  index: index",
        'index.md' => "# Index\n",
    ]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('points at itself');
});

it('warns about a redirect a real page will always shadow', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\n  - installation\nredirects:\n  installation: index",
        'index.md' => "# Index\n",
        'installation.md' => "# Installation\n",
    ]);

    expect($result->passed())->toBeTrue()
        ->and(messages($result, 'warnings'))->toContain('will never fire');
});

it('fails a page whose front matter is not valid YAML', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index",
        'index.md' => "---\ntitle: \"unterminated\n---\n\n# Index\n",
    ]);

    expect($result->failed())->toBeTrue()
        ->and(messages($result))->toContain('front matter is not valid YAML');
});

it('reports every problem at once rather than the first', function (): void {
    $result = validate([
        'docs.yml' => "navigation:\n  - index\n  - missing-one\n  - missing-two",
        'index.md' => "# Index\n",
    ]);

    expect($result->errors())->toHaveCount(2);
});
