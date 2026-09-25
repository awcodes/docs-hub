<?php

declare(strict_types=1);

/*
| The boundaries this application is organised around, asserted rather than
| described. Each one is cheap to violate by accident and expensive to notice
| afterwards, which is the whole reason they are asserted.
*/

arch('every class declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('nothing debugs in production')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

/*
| The source-provider abstraction.
|
| It exists so that synchronization can be built and tested without GitHub, and
| an abstraction with no test asserting the dependency direction is only a
| naming convention.
*/

arch('every documentation source implements the contract')
    ->expect('App\Documentation\Sources')
    ->toImplement(App\Documentation\Sources\DocumentationSource::class)
    ->ignoring(App\Documentation\Sources\DocumentationSource::class);

arch('the documentation domain does not know about Saloon')
    ->expect('App\Documentation')
    ->not->toUse('Saloon');

arch('nothing but synchronization reaches for a source')
    ->expect('App\Documentation\Sources')
    ->toOnlyBeUsedIn([
        'App\Documentation\Sources',
        'App\Documentation\Actions',
        'App\Console\Commands',
    ]);

/*
| Snapshot storage is reached through one class.
|
| The filesystem abstraction is there so that a multi-node deployment can move
| snapshots to object storage. That swap is only contained if the disk has one
| caller — the moment a controller reaches for it directly, the seam is gone.
*/

arch('only the snapshot store reaches the documentation disk')
    ->expect(Illuminate\Support\Facades\Storage::class)
    ->toOnlyBeUsedIn('App\Documentation\Storage');

/*
| Only the GitHub source talks to the GitHub integration.
|
| The pair is the point. The first keeps the HTTP client out of the domain, so
| replacing Saloon is a change inside one folder; the second keeps the
| integration out of everything that is not a source, so synchronization can go
| on being tested without GitHub.
*/

arch('only a documentation source talks to the GitHub integration')
    ->expect('App\Http\Integrations\GitHub')
    ->toOnlyBeUsedIn([
        'App\Http\Integrations\GitHub',
        'App\Documentation\Sources',
    ]);
