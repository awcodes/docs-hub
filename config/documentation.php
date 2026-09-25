<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Local Source Paths
    |--------------------------------------------------------------------------
    |
    | Repository checkouts the hub may read documentation from directly, keyed
    | by project slug, so a documentation change can be previewed here before
    | it is pushed.
    |
    | Configured per project rather than by scanning one directory, so that a
    | repository checked out anywhere else mounts the same way. A project with
    | no entry here cannot use the local source, which is the intended default:
    | in production every project comes from GitHub.
    |
    | Set through the environment rather than here, because the values are
    | absolute paths on one developer's machine and this file is committed:
    |
    |     DOCS_LOCAL_SOURCES="curator=/Users/you/Dev/awcodes/curator"
    |
    | Comma-separated for more than one.
    |
    */

    'local_sources' => collect(explode(',', (string) env('DOCS_LOCAL_SOURCES', '')))
        ->filter(fn (string $pair): bool => str_contains($pair, '='))
        ->mapWithKeys(function (string $pair): array {
            [$slug, $path] = explode('=', mb_trim($pair), 2);

            return [mb_trim($slug) => mb_rtrim(mb_trim($path), '/')];
        })
        ->all(),

    /*
    |--------------------------------------------------------------------------
    | GitHub
    |--------------------------------------------------------------------------
    |
    | Optional. Every documented repository is public, so synchronization
    | works without a token — but anonymous requests to the GitHub API are
    | limited to 60 an hour, and a token raises that to 5,000. A fine-grained
    | token with no extra permissions is enough.
    |
    */

    'github' => [
        'token' => env('DOCS_GITHUB_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved Root Paths
    |--------------------------------------------------------------------------
    |
    | Documentation lives at the root of the site — `/curator/1.x/installation`
    | — so a project slug occupies the same position as every other top-level
    | path. These are the ones the application has already spoken for, and a
    | project may not be registered under any of them.
    |
    | Stated rather than relied upon: without it, whether `/admin` reaches the
    | panel or the documentation router would come down to which service
    | provider happened to register its routes first.
    |
    */

    'reserved_paths' => [
        'admin',
        'assets',
        'livewire',
        'storage',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Assets
    |--------------------------------------------------------------------------
    |
    | What `/assets/{project}/{version}/{path}` will serve, and as what. An
    | extension that is not listed here is a 404: the media type is decided
    | from this table rather than sniffed from the file, so a repository cannot
    | get something served as a type it was not registered as.
    |
    | `max_age` is kept short because an asset URL names a version rather than
    | a commit, so a later sync can publish different bytes at the same URL.
    |
    */

    'assets' => [
        'types' => [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
        ],

        'max_age' => (int) env('DOCS_ASSET_MAX_AGE', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshots
    |--------------------------------------------------------------------------
    |
    | Synchronized documentation is written to `disk` under
    | `{project}/{version}/{commit}`, and a version is published by pointing
    | its `active_snapshot` column at one of those directories. Nothing is ever
    | renamed into place: rename is atomic on a local filesystem and is a copy
    | followed by a delete on object storage, so publication must not depend on
    | it.
    |
    | `retain` is how many snapshots a version keeps, the active one included.
    | Superseded snapshots are what make a rollback a pointer update rather
    | than a resynchronization, so this should not be 1.
    |
    */

    'snapshots' => [
        'disk' => env('DOCS_DISK', 'documentation'),
        'retain' => env('DOCS_SNAPSHOT_RETAIN', 3),
    ],

];
