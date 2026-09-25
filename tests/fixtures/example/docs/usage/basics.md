# Basics

## Creating a widget

```php
use Acme\Example\Models\Widget;

Widget::create([
    'name' => 'Welcome banner',
    'body' => 'Hello there.',
]);
```

## Rendering a widget

```blade
<x-example::widget name="Welcome banner" />
```

A widget that does not exist renders nothing rather than throwing.

## Ordering

Widgets are ordered by their `sort` column. The Filament resource lets you drag
them into place.

## Soft deletion

Deleted widgets are soft deleted and can be restored from the resource's
trash filter. Run `example:prune` to remove them for good — see
[Commands](commands.md).
