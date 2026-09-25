# Commands

## Importing widgets

```shell
php artisan example:import widgets.json
```

The file is a JSON array of objects with `name` and `body` keys. Existing
widgets with the same name are updated.

## Pruning deleted widgets

```shell
php artisan example:prune --days=30
```

Permanently removes widgets that were soft deleted more than `--days` ago.

## Scheduling

```php
Schedule::command('example:prune')->daily();
```

> [!CAUTION]
> Pruning cannot be undone.
