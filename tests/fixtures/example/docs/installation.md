# Installation

## Requiring the package

```shell
composer require acme/example
```

The service provider is discovered automatically.

## Publishing the migration

```shell
php artisan vendor:publish --tag=example-migrations
php artisan migrate
```

> [!IMPORTANT]
> The migration creates a `widgets` table. If your application already has one,
> set `example.table` before migrating — see [Configuration](configuration.md).

## Registering the Filament plugin

```php
use Acme\Example\ExamplePlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        ExamplePlugin::make(),
    ]);
}
```

## Next steps

Create your first widget with the [Basics](usage/basics.md) guide.
