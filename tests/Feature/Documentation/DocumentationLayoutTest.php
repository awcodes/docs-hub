<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
| What is worth asserting is structure and orientation —
| that a reader can tell where they are and get somewhere else — rather than
| particular utility classes, which are the part most likely to change while the
| layout stays the same.
*/

beforeEach(function (): void {
    Storage::fake('documentation');
});

afterEach(function (): void {
    File::deleteDirectory(documentationScratch());
});

it('renders a page inside the reading surface', function (): void {
    publishDocumentation(['index' => "# Example\n\nThe shared foundation.\n"]);

    $this->get('/example/1.x')
        ->assertSuccessful()
        ->assertSee('docs-prose', escape: false)
        ->assertSee('The shared foundation.');
});

it('titles the tab with the page and the project', function (): void {
    publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);

    $page = App\Models\DocumentationPage::query()->where('slug', 'installation')->firstOrFail();
    $page->update(['title' => 'Installation']);

    $this->get('/example/1.x/installation')
        ->assertSee('<title>Installation · '.$page->version->project->name.'</title>', escape: false);
});

it('lets search engines index the documentation', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x')
        ->assertSuccessful()
        ->assertDontSee('name="robots"', escape: false);
});

it('names the project and version in the header', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $project = Project::query()->where('slug', 'example')->firstOrFail();

    $this->get('/example/1.x')
        ->assertSee($project->name)
        ->assertSee('1.x')
        ->assertSee(config('app.name'));
});

it('omits the version from the header of a rolling project', function (): void {
    publishDocumentation(['index' => "# Toolkit\n"], slug: 'toolkit', version: 'main', rolling: true);

    $this->get('/toolkit')
        ->assertSuccessful()
        ->assertDontSee('>main<', escape: false);
});

it('shows a guest neither the admin link nor a way to sign out', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x')
        ->assertSuccessful()
        ->assertDontSee('>Admin</a>', escape: false)
        ->assertDontSee('Sign out');
});

it('offers a signed-in administrator the panel and a way out', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $this->actingAs(User::factory()->create())
        ->get('/example/1.x')
        ->assertSee('>Admin</a>', escape: false)
        ->assertSee('Sign out')
        ->assertSee(route('filament.admin.auth.logout'), escape: false);
});

it('links to the repository the documentation came from', function (): void {
    publishDocumentation(['index' => "# Example\n"]);

    $project = Project::query()->where('slug', 'example')->firstOrFail();

    $this->get('/example/1.x')
        ->assertSee('https://github.com/'.$project->repository, escape: false);
});

it('renders the navigation the manifest ordered, not the pages it indexed', function (): void {
    publishDocumentation(
        [
            'index' => "# Example\n",
            'installation' => "# Installation\n",
            'usage/authentication' => "# Authentication\n",
            'unlisted' => "# Half written\n",
        ],
        navigation: [
            ['page' => 'index'],
            ['page' => 'installation'],
            ['label' => 'Usage', 'children' => ['usage/authentication']],
        ],
    );

    $response = $this->get('/example/1.x/installation');

    $response->assertSuccessful()
        ->assertSee('Usage')
        ->assertSee('/example/1.x/usage/authentication', escape: false)
        ->assertSee('aria-current="page"', escape: false);

    // Indexed, routable, searchable — and absent from the sidebar.
    expect($response->getContent())->not->toContain('/example/1.x/unlisted');

    $this->get('/example/1.x/unlisted')->assertSuccessful();
});

it('names the project and version in the sidebar, where the context lives', function (): void {
    $version = publishDocumentation(['index' => "# Example\n"]);

    $this->get('/example/1.x')
        ->assertSee($version->project->name)
        ->assertSee($version->project->description)
        ->assertSee('Current');
});

it('omits the version line for a rolling project rather than disabling it', function (): void {
    publishDocumentation(['index' => "# Toolkit\n"], slug: 'toolkit', version: 'main', rolling: true);

    $this->get('/toolkit')
        ->assertSuccessful()
        ->assertDontSee('>main<', escape: false);
});

it('walks to the next page in reading order, flattening groups', function (): void {
    publishDocumentation(
        [
            'index' => "# Example\n",
            'installation' => "# Installation\n",
            'usage/authentication' => "# Authentication\n",
        ],
        navigation: [
            ['page' => 'index'],
            ['label' => 'Setup', 'children' => ['installation']],
            ['label' => 'Usage', 'children' => ['usage/authentication']],
        ],
    );

    // A group label is not a page, so moving on from the last entry of one
    // group lands on the first entry of the next.
    $this->get('/example/1.x/installation')
        ->assertSee('/example/1.x/usage/authentication', escape: false)
        ->assertSee('Previous')
        ->assertSee('Next');
});

it('gives an unlisted page no neighbours rather than arbitrary ones', function (): void {
    publishDocumentation(
        ['index' => "# Example\n", 'unlisted' => "# Half written\n"],
        navigation: [['page' => 'index']],
    );

    $this->get('/example/1.x/unlisted')
        ->assertSuccessful()
        ->assertDontSee('Previous')
        ->assertDontSee('Next');
});

it('links each page to where it is edited, at the ref it was read from', function (): void {
    publishDocumentation(['index' => "# Example\n", 'installation' => "# Installation\n"]);

    $this->get('/example/1.x/installation')
        ->assertSee('Edit this page on GitHub')
        ->assertSee('/blob/1.x/docs/installation.md', escape: false);
});

it('builds an on-this-page list from the headings of the page', function (): void {
    publishDocumentation(['index' => <<<'MD'
        # Theme composition

        ## One filename, three jobs

        ## Why the application composes
        MD]);

    $this->get('/example/1.x')
        ->assertSee('On this page')
        ->assertSee('#one-filename-three-jobs', escape: false)
        ->assertSee('#why-the-application-composes', escape: false);
});

it('omits the on-this-page list when there is nothing to list', function (): void {
    publishDocumentation(['index' => "# Example\n\nOne paragraph and no sections.\n"]);

    $this->get('/example/1.x')
        ->assertDontSee('On this page');
});

it('renders a real Example page end to end', function (): void {
    $markdown = (string) file_get_contents(
        base_path('tests/fixtures/example/docs/architecture/theme-composition.md'),
    );

    publishDocumentation(['index' => "# Example\n", 'architecture/theme-composition' => $markdown]);

    $this->get('/example/1.x/architecture/theme-composition')
        ->assertSuccessful()
        ->assertSee('docs-prose', escape: false)
        // Phiki highlighted the CSS, rather than leaving it as plain text —
        // the most visible defect, on the page most made of CSS.
        ->assertSee('pre class="phiki', escape: false)
        ->assertSee('On this page');
});
