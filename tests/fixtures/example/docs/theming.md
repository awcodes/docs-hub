# Theming

The widget component ships unstyled apart from a handful of CSS custom
properties.

## Custom properties

```css
.example-widget {
    --example-widget-padding: 1rem;
    --example-widget-radius: 0.5rem;
    --example-widget-border: var(--color-gray-200);
}
```

Redefine any of them in your own stylesheet to change the look.

## Tailwind

If your application uses Tailwind, add the package's views to its sources so
the utilities they use are generated:

```css
@import 'tailwindcss';

@source '../../vendor/acme/example/resources/views/**/*.blade.php';
```

## Dark mode

The component reads `prefers-color-scheme` and a `.dark` ancestor, whichever
your application uses. The details are in
[Theme composition](architecture/theme-composition.md).
