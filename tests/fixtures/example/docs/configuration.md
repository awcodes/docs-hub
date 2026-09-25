# Configuration

Publish the configuration file to change any default:

```shell
php artisan vendor:publish --tag=example-config
```

## Table name

```php
'table' => env('EXAMPLE_TABLE', 'widgets'),
```

Change this before running the migration. Changing it afterwards needs a
migration of your own.

## Cache

```php
'cache' => [
    'enabled' => env('EXAMPLE_CACHE', true),
    'ttl' => 3600,
],
```

Rendered widgets are cached for `ttl` seconds. Saving a widget clears its entry.

## Feature switches

| Key | Default | Effect |
|---|---|---|
| `features.resource` | `true` | Registers the Filament resource. |
| `features.commands` | `true` | Registers the Artisan commands. |

> [!TIP]
> Turning the resource off does not remove the model. Widgets can still be
> managed in code.
