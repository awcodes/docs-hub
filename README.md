# awcodes Docs

The documentation hub for awcodes open source packages: one application that
synchronizes, indexes, renders and searches the documentation each package
keeps in its own repository.

> Repositories own documentation content. The hub owns distribution and
> presentation.

[![MIT Licensed](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

## How it works

Repositories own their documentation. The hub pulls it, never the other way
round.

```
docs.yml + Markdown          in each repository, reviewed in its own pull request
        |
        v
docs:sync                    resolve ref -> fetch docs -> validate -> snapshot -> index
        |
        v
/{project}/{version}/{page}  rendered, searchable, public
```

A version is published by pointing `project_versions.active_snapshot` at a
commit-scoped directory. Nothing is renamed into place, so a sync that fails at
any point leaves the previous snapshot serving.

Reading the documentation needs no account. Only the admin panel at `/admin`,
where projects and versions are registered and synced, requires signing in.

### What the reader gets

- Markdown rendered with GitHub alert callouts (`> [!NOTE]`, `> [!WARNING]`, …)
  and Phiki syntax highlighting.
- Relative links between pages resolved per version, so documentation can be
  cherry-picked between branches without rewriting a link.
- Navigation built from each repository's `docs.yml`, with previous/next links
  in reading order.
- Project and version switchers that keep the reader on the equivalent page.
- A notice on legacy versions pointing at the current one.
- Cross-project search (<kbd>⌘K</kbd>), weighted towards current versions.
- Light and dark themes, and a mobile navigation drawer.

## The documentation contract

A repository can publish in one of two ways:

1. **Structured** — a `docs/` directory with a `docs.yml` manifest and Markdown
   pages.
2. **README** — no `docs/` directory, so the repository's `README.md` is
   published as a single page.

`docs.yml` describes the navigation, and optionally a title and redirects for
renamed pages:

```yaml
version: 1

title: Curator

navigation:
  - index
  - installation

  - label: Usage
    children:
      - usage/basics
      - usage/configuration

redirects:
  getting-started: installation
```

Pages may carry front matter. `slug` replaces the last segment of the page's
URL; `title` and `description` override what the hub reads from the page.

```markdown
---
title: Theme composition
description: How the package's styles are layered.
---

# Theme composition
```

Images and other assets live under `docs/assets/` and are served at
`/assets/{project}/{version}/{path}`.

An image can be limited to one theme with the `#gh-light-mode-only` and
`#gh-dark-mode-only` fragments, a GitHub convention. The hub shows only the image
matching the reader's theme:

```markdown
![The editor](assets/editor-light.png#gh-light-mode-only)
![The editor](assets/editor-dark.png#gh-dark-mode-only)
```

A page that exists but is left out of `docs.yml` can still be opened and
searched, but doesn't appear in the sidebar. That lets half-written pages be
previewed before they're listed.

## Getting started

```bash
composer setup                # install, .env, key, migrate, npm install, build
php artisan make:filament-user
```

With Herd the app is served at `https://awcodes-docs-hub.test`. To run the
server, queue worker, log tail and Vite together instead:

```bash
composer dev
```

### Registering a project

Sign in at `/admin`, create a **Project** (its slug is the first URL segment,
its repository is `owner/name` on GitHub), then add one or more **Versions**,
each pointing at a git ref. Mark one version as the default; unversioned URLs
redirect to it.

A project is either:

- **Versioned** — URLs carry the version: `/curator/1.x/installation`.
- **Rolling** — exactly one version and no version segment: `/curator/installation`.

### Synchronizing

Sync from the dashboard widget or a project's header action in the panel, or
from the command line:

```bash
php artisan docs:sync                    # every registered version
php artisan docs:sync curator            # one project
php artisan docs:sync curator 1.x        # one version
php artisan docs:sync --force            # republish an unchanged commit
```

Every documented repository is public, so no credential is needed. Setting
`DOCS_GITHUB_TOKEN` to a fine-grained token (no extra permissions) raises
GitHub's API rate limit from 60 to 5,000 requests an hour.

### Previewing unpushed documentation

To preview a change before pushing it, mount a local checkout for a project and
sync from there instead of GitHub. A mounted project always reads from its
checkout, so leave this empty in production.

```dotenv
DOCS_LOCAL_SOURCES="curator=/Users/you/Dev/awcodes/curator"
```

Comma-separate more than one `slug=path` pair.

### Validating in a pull request

`docs:validate` checks a documentation root against the contract. It needs no
database, no network and no registered project, which is what lets it run as a
pull request check inside each documented repository:

```bash
php artisan docs:validate path/to/repo/docs
```

Errors fail the check; warnings (a page listed twice, a redirect shadowed by a
real page) are reported without failing it.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `DOCS_GITHUB_TOKEN` | — | Optional GitHub token, for the higher rate limit. |
| `DOCS_LOCAL_SOURCES` | — | `slug=path` pairs to sync from local checkouts. |
| `DOCS_DISK` | `documentation` | Filesystem disk that holds snapshots. |
| `DOCS_DISK_DRIVER` | `local` | Driver for the `documentation` disk. |
| `DOCS_SNAPSHOT_RETAIN` | `3` | Snapshots kept per version, so a rollback is a pointer change. |
| `DOCS_ASSET_MAX_AGE` | `3600` | `Cache-Control` max-age, in seconds, for served assets. |

The rest lives in `config/documentation.php`, including the reserved root paths
(`admin`, `assets`, `livewire`, `storage`, `up`) that no project may use as its
slug.

## Working on it

```bash
composer test          # Rector, Pint, PHPStan, then Pest in parallel
composer lint          # apply Pint
composer refactor      # apply Rector
```

| Tool | Job |
|---|---|
| Rector | automated refactoring, with Pest's coding-style set |
| Pint | formatting, including Blade templates |
| PHPStan + Larastan | static analysis at level 5 |
| Pest | tests, with the arch, Livewire, PHPStan and Rector plugins |
| Laravel Boost | agent guidelines, skills and MCP for this application |

The tests run against `tests/fixtures/example`, a fictional package's `docs/`
and `README.md` kept in this repository, so they never depend on a sibling
checkout.

## License

MIT. See [LICENSE.md](LICENSE.md).
