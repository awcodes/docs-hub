<?php

declare(strict_types=1);

use App\Documentation\Markdown\MarkdownRenderer;

function render(string $markdown): App\Documentation\Data\RenderedPage
{
    return (new MarkdownRenderer)->render($markdown);
}

it('renders ordinary Markdown', function (): void {
    $html = render("# Installation\n\nRun `composer install`.\n")->html;

    expect($html)->toContain('<h1')
        ->and($html)->toContain('<code>composer install</code>');
});

it('renders GitHub-flavoured tables', function (): void {
    $html = render(<<<'MD'
        | Command | What it does |
        |---|---|
        | `composer test` | Runs the gate |
        MD)->html;

    expect($html)->toContain('<table>')
        ->and($html)->toContain('<th>Command</th>')
        ->and($html)->toContain('Runs the gate');
});

it('escapes raw HTML rather than rendering it', function (): void {
    // Every documented repository can write to this origin, and the origin
    // shares a session with the admin panel.
    $html = render("Hello <script>alert('x')</script> there\n")->html;

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('refuses an unsafe link', function (): void {
    $html = render('[click](javascript:alert(1))')->html;

    expect($html)->not->toContain('javascript:');
});

it('gives every heading a clean anchor', function (): void {
    $page = render(<<<'MD'
        # Theme composition

        ## One filename, three jobs

        ### A deeper point
        MD);

    expect($page->headings)->toBe([
        ['level' => 1, 'title' => 'Theme composition', 'anchor' => 'theme-composition'],
        ['level' => 2, 'title' => 'One filename, three jobs', 'anchor' => 'one-filename-three-jobs'],
        ['level' => 3, 'title' => 'A deeper point', 'anchor' => 'a-deeper-point'],
    ]);
});

it('uses the anchors it reports', function (): void {
    $page = render("## One filename, three jobs\n");

    expect($page->html)->toContain('id="one-filename-three-jobs"')
        ->and($page->headings[0]['anchor'])->toBe('one-filename-three-jobs');
});

it('reads a heading title through inline formatting', function (): void {
    $page = render("## Using `composer test` well\n");

    expect($page->headings[0]['title'])->toBe('Using composer test well');
});

it('marks an external link and leaves an internal one alone', function (): void {
    config()->set('app.url', 'https://docs.acme.test');

    $html = render('[out](https://example.com) and [in](https://docs.acme.test/example)')->html;

    expect($html)->toContain('external-link')
        ->and(mb_substr_count($html, 'external-link'))->toBe(1);
});

it('does not print front matter into the page', function (): void {
    $page = render("---\ntitle: Theme composition\n---\n\n# Theme composition\n");

    expect($page->html)->not->toContain('title: Theme composition')
        ->and($page->frontMatter?->title)->toBe('Theme composition');
});

it('prefers the front matter title over the first heading', function (): void {
    expect(render("---\ntitle: Declared\n---\n\n# Written\n")->title())->toBe('Declared')
        ->and(render("# Written\n")->title())->toBe('Written')
        ->and(render("Just a paragraph.\n")->title())->toBeNull();
});

/*
| Callouts.
*/

it('renders every GitHub alert type', function (string $alert, string $variant): void {
    $html = render("> [!{$alert}]\n> Something worth knowing.\n")->html;

    expect($html)->toContain("callout callout-{$variant}")
        ->and($html)->toContain('Something worth knowing.')
        ->and($html)->not->toContain("[!{$alert}]")
        ->and($html)->not->toContain('<blockquote>');
})->with([
    ['NOTE', 'note'],
    ['TIP', 'tip'],
    ['IMPORTANT', 'important'],
    ['WARNING', 'warning'],
    ['CAUTION', 'danger'],
]);

it('labels a callout in text rather than in CSS', function (): void {
    $html = render("> [!WARNING]\n> Mind the gap.\n")->html;

    expect($html)->toContain('callout-label')
        ->and($html)->toContain('Warning');
});

it('keeps the contents of a callout ordinary Markdown', function (): void {
    $html = render(<<<'MD'
        > [!NOTE]
        > Run `composer test` first.
        >
        > - One
        > - Two
        MD)->html;

    expect($html)->toContain('<code>composer test</code>')
        ->and($html)->toContain('<li>One</li>');
});

it('leaves an ordinary blockquote alone', function (): void {
    $html = render("> Just a quotation.\n")->html;

    expect($html)->toContain('<blockquote>')
        ->and($html)->not->toContain('callout');
});

it('leaves a blockquote that merely mentions a marker alone', function (): void {
    $html = render("> Write `[!NOTE]` to open a callout.\n")->html;

    expect($html)->toContain('<blockquote>')
        ->and($html)->not->toContain('callout-note');
});

it('leaves an unknown marker alone', function (): void {
    $html = render("> [!SPOILER]\n> Nothing doing.\n")->html;

    expect($html)->toContain('<blockquote>')
        ->and($html)->not->toContain('callout');
});

it('leaves a marker with no body alone', function (): void {
    $html = render("> [!NOTE]\n")->html;

    expect($html)->not->toContain('callout');
});

/*
| Highlighting. The warning there is specific: a CSS block
| rendering as plain text is the most visible defect a documentation site can
| have, and Example's theme-composition page is mostly CSS.
*/

it('highlights every language the documentation is written in', function (string $language, string $code): void {
    $html = render("```{$language}\n{$code}\n```")->html;

    expect($html)->toContain('<pre')
        ->and($html)->toContain('style="color:')
        ->and($html)->not->toContain("<code>{$code}</code>");
})->with([
    'php' => ['php', '<?php echo $name;'],
    'blade' => ['blade', '@if ($user) {{ $user->name }} @endif'],
    'css' => ['css', '.callout { color: red; }'],
    'yaml' => ['yaml', "version: 1\nnavigation:\n  - index"],
    'json' => ['json', '{"name": "acme/example"}'],
    'shell' => ['shell', 'composer test --no-interaction'],
]);

it('renders an unlabelled code fence without failing', function (): void {
    expect(render("```\nplain text\n```")->html)->toContain('plain text');
});

/*
| The whole of Example's documentation, as the thing this has to publish.
*/

it('renders every page of Example\'s documentation', function (): void {
    $pages = Symfony\Component\Finder\Finder::create()
        ->files()
        ->in(base_path('tests/fixtures/example/docs'))
        ->name('*.md');

    $rendered = 0;

    foreach ($pages as $page) {
        $result = render($page->getContents());

        expect($result->html)->not->toBeEmpty()
            ->and($result->headings)->not->toBeEmpty()
            ->and($result->html)->not->toContain('[!');

        $rendered++;
    }

    expect($rendered)->toBe(11);
});
