# Authentication

Example does not add a login of its own. It asks your application who may
manage widgets.

## The gate

```php
Gate::define('manage-widgets', fn (User $user): bool => $user->is_admin);
```

Without a `manage-widgets` gate, nobody can manage widgets from the panel.

> [!WARNING]
> Defining the gate as `fn () => true` lets every signed-in user edit every
> widget. That is rarely what you want.

## Policies

If you prefer policies, register one for `Widget` and the resource will use it
instead of the gate.

## Rendering is public

The Blade component does not check the gate. A widget that is rendered is
visible to whoever can see the page.
