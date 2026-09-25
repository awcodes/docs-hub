# Testing

## Running the suite

```shell
composer test
```

That runs Rector, Pint, PHPStan and Pest in that order and stops at the first
failure.

## Writing a test

```php
it('renders a widget by name', function (): void {
    Widget::factory()->create(['name' => 'Banner', 'body' => 'Hello']);

    $this->blade('<x-example::widget name="Banner" />')
        ->assertSee('Hello');
});
```

## Fixtures

Factories exist for every model. Prefer them to building records by hand.
