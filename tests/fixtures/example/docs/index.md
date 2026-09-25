# Example

Example is a Laravel package that adds a small, well-behaved feature to an
application: a registry of widgets, a Filament resource to manage them, and a
Blade component to render them.

> [!NOTE]
> This package is fictional. It exists so the documentation hub has something
> realistic to synchronize, render and search in its tests.

## What it provides

- A `Widget` model and migration.
- A Filament resource for managing widgets.
- A `<x-example::widget>` Blade component.
- Two Artisan commands for importing and pruning widgets.

## Where to start

- [Installation](installation.md) — requiring the package and publishing its
  migration.
- [Configuration](configuration.md) — every option in `config/example.php`.
- [Basics](usage/basics.md) — creating and rendering a widget.
- [Authentication](usage/authentication.md) — who may manage widgets.
- [Theming](theming.md) — styling the component to match an application.

If you are changing the package itself, read
[Boundaries](architecture/boundaries.md) and
[Theme composition](architecture/theme-composition.md) first, then
[Development](contributing/development.md).

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.4 or later |
| Laravel | 13.x |
| Filament | 5.x |
