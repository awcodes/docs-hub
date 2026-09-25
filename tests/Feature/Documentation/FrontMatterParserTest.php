<?php

declare(strict_types=1);

use App\Documentation\Markdown\FrontMatterParser;
use Symfony\Component\Yaml\Exception\ParseException;

function frontMatter(string $markdown): App\Documentation\Data\FrontMatter
{
    return (new FrontMatterParser)->parse($markdown);
}

it('reads the block a package already writes', function (): void {
    $parsed = frontMatter((string) file_get_contents(
        base_path('tests/fixtures/example/docs/architecture/theme-composition.md'),
    ));

    expect($parsed->title)->toBe('Theme composition')
        ->and($parsed->description)->toStartWith('How the package\'s styles are layered')
        ->and($parsed->slug)->toBeNull();
});

it('reads an explicit slug', function (): void {
    expect(frontMatter("---\nslug: installation\n---\n\n# Setting up\n")->slug)
        ->toBe('installation');
});

it('treats a page with no front matter as carrying none', function (): void {
    expect(frontMatter("# Installation\n\nSome prose.\n")->isEmpty())->toBeTrue();
});

it('does not mistake a horizontal rule for front matter', function (): void {
    $parsed = frontMatter(<<<'MD'
        # Installation

        Some prose.

        ---

        title: not front matter
        MD);

    expect($parsed->isEmpty())->toBeTrue();
});

it('reads a block written with Windows line endings', function (): void {
    expect(frontMatter("---\r\ntitle: Installation\r\n---\r\n\r\n# Installation\r\n")->title)
        ->toBe('Installation');
});

it('ignores an empty front matter block', function (): void {
    expect(frontMatter("---\n---\n\n# Installation\n")->isEmpty())->toBeTrue();
});

it('ignores a field that is not a string', function (): void {
    expect(frontMatter("---\ntitle: [a, b]\ndescription: ''\n---\n")->isEmpty())->toBeTrue();
});

it('reports front matter that is not valid YAML', function (): void {
    frontMatter("---\ntitle: \"unterminated\n---\n\n# Installation\n");
})->throws(ParseException::class);

it('reads a block containing multibyte characters', function (): void {
    $parsed = frontMatter("---\ntitle: Thème — composition\ndescription: Ünicöde\n---\n\n# Thème\n");

    expect($parsed->title)->toBe('Thème — composition')
        ->and($parsed->description)->toBe('Ünicöde');
});
