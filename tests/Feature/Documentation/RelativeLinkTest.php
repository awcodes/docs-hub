<?php

declare(strict_types=1);

use App\Documentation\Data\PageContext;
use App\Documentation\Markdown\MarkdownRenderer;
use App\Enums\VersioningMode;
use App\Models\Project;
use App\Models\ProjectVersion;

/*
| Authors write ordinary relative Markdown and never hub URLs, so
| the same page renders correct links on 1.x and on 2.x without being edited.
| That property is what lets a documentation fix be cherry-picked between
| supported branches, so most of these tests are about it directly.
*/

/** @param array<string, string> $resolved */
function pageContext(string $page = 'index', string $version = '1.x', array $resolved = []): PageContext
{
    // Reused rather than recreated: several tests build two contexts to show
    // one page resolving differently on two versions of the same project.
    $project = Project::query()->firstWhere('slug', 'example')
        ?? Project::factory()->create(['slug' => 'example']);

    return new PageContext(
        project: $project,
        version: ProjectVersion::factory()->for($project)->create(['version' => $version]),
        page: $page,
        resolved: $resolved,
    );
}

function renderIn(string $markdown, ?PageContext $context = null): string
{
    return (new MarkdownRenderer)->render($markdown, $context ?? pageContext())->html;
}

it('resolves a relative page link against the current version', function (): void {
    expect(renderIn('[Boundaries](architecture/boundaries.md)'))
        ->toContain('href="/example/1.x/architecture/boundaries"');
});

it('resolves the same link differently on another version', function (): void {
    $markdown = '[Configuration](configuration.md)';

    expect(renderIn($markdown, pageContext(version: '1.x')))
        ->toContain('href="/example/1.x/configuration"')
        ->and(renderIn($markdown, pageContext(version: '2.x')))
        ->toContain('href="/example/2.x/configuration"');
});

it('resolves a link relative to the page that wrote it', function (): void {
    expect(renderIn('[Theme](theme-composition.md)', pageContext(page: 'architecture/boundaries')))
        ->toContain('href="/example/1.x/architecture/theme-composition"');
});

it('walks back out of a directory', function (): void {
    expect(renderIn('[Install](../installation.md)', pageContext(page: 'architecture/boundaries')))
        ->toContain('href="/example/1.x/installation"');
});

it('walks across directories', function (): void {
    expect(renderIn('[Auth](../usage/authentication.md)', pageContext(page: 'architecture/boundaries')))
        ->toContain('href="/example/1.x/usage/authentication"');
});

it('keeps a fragment pointing at a heading on another page', function (): void {
    expect(renderIn('[Drivers](configuration.md#drivers)'))
        ->toContain('href="/example/1.x/configuration#drivers"');
});

it('sends a link to index at the version root', function (): void {
    expect(renderIn('[Home](index.md)', pageContext(page: 'installation')))
        ->toContain('href="/example/1.x"');
});

it('honours a slug that front matter moved', function (): void {
    $context = pageContext(resolved: ['architecture/sso-integration' => 'architecture/sso']);

    expect(renderIn('[SSO](architecture/sso-integration.md)', $context))
        ->toContain('href="/example/1.x/architecture/sso"');
});

it('drops the version segment for a rolling project', function (): void {
    $project = Project::factory()->application()->create(['slug' => 'toolkit']);
    $version = ProjectVersion::factory()->for($project)->create(['version' => 'main']);

    $html = (new MarkdownRenderer)->render(
        '[Deploys](operations/deploys.md)',
        new PageContext($project, $version, 'index'),
    )->html;

    expect($project->versioning_mode)->toBe(VersioningMode::Rolling)
        ->and($html)->toContain('href="/toolkit/operations/deploys"');
});

it('resolves an image to the authenticated asset route', function (): void {
    expect(renderIn('![Panel conventions](assets/panel-conventions.png)'))
        ->toContain('src="/assets/example/1.x/panel-conventions.png"');
});

it('resolves an asset from a nested page', function (): void {
    expect(renderIn('![Diagram](../assets/diagram.png)', pageContext(page: 'architecture/boundaries')))
        ->toContain('src="/assets/example/1.x/diagram.png"');
});

it('namespaces assets so two versions can ship different screenshots', function (): void {
    $markdown = '![Panel](assets/panel.png)';

    expect(renderIn($markdown, pageContext(version: '1.x')))
        ->toContain('src="/assets/example/1.x/panel.png"')
        ->and(renderIn($markdown, pageContext(version: '2.x')))
        ->toContain('src="/assets/example/2.x/panel.png"');
});

it('keeps a theme fragment on an image so the stylesheet can scope it', function (): void {
    expect(renderIn('![Editor](assets/editor-dark.png#gh-dark-mode-only)'))
        ->toContain('src="/assets/example/1.x/editor-dark.png#gh-dark-mode-only"');
});

it('resolves an asset linked rather than embedded', function (): void {
    expect(renderIn('[The diagram](assets/diagram.svg)'))
        ->toContain('href="/assets/example/1.x/diagram.svg"');
});

/*
| What it must not touch.
*/

it('leaves an absolute URL alone', function (): void {
    expect(renderIn('[GitHub](https://github.com/acme/example)'))
        ->toContain('href="https://github.com/acme/example"');
});

it('leaves a mailto alone', function (): void {
    expect(renderIn('[Mail](mailto:someone@example.com)'))
        ->toContain('href="mailto:someone@example.com"');
});

it('leaves an in-page anchor alone', function (): void {
    expect(renderIn('[Below](#drivers)'))->toContain('href="#drivers"');
});

it('leaves a site-absolute path alone', function (): void {
    expect(renderIn('[Elsewhere](/widgets/2.x/pages)'))->toContain('href="/widgets/2.x/pages"');
});

it('leaves a link that climbs out of the documentation root alone', function (): void {
    expect(renderIn('[Escape](../../../etc/passwd)', pageContext(page: 'architecture/boundaries')))
        ->toContain('href="../../../etc/passwd"');
});

it('leaves an unrecognized relative path alone', function (): void {
    expect(renderIn('[Source](src/Example.php)'))->toContain('href="src/Example.php"');
});

it('rewrites nothing without a context', function (): void {
    expect((new MarkdownRenderer)->render('[Config](configuration.md)')->html)
        ->toContain('href="configuration.md"');
});

/*
| Example's own documentation, which is the thing that has to publish
| unchanged.
*/

it('resolves every relative link in Example\'s index page', function (): void {
    $html = renderIn(
        (string) file_get_contents(base_path('tests/fixtures/example/docs/index.md')),
    );

    expect($html)->not->toContain('.md"')
        ->and($html)->toContain('href="/example/1.x/');
});
