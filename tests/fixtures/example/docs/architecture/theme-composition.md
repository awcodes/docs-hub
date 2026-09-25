---
title: Theme composition
description: How the package's styles are layered so that an application can override any of them.
---

# Theme composition

The component's styles are built in three layers, each overridable by the one
after it.

## Tokens

```css
:root {
    --example-widget-padding: 1rem;
    --example-widget-radius: 0.5rem;
}
```

Tokens are the only thing an application should need to change.

## Component styles

```css
.example-widget {
    padding: var(--example-widget-padding);
    border-radius: var(--example-widget-radius);
}
```

## Application overrides

Anything the application declares after the package's stylesheet wins. There
is no `!important` anywhere in the package, so an ordinary selector is enough.

## Dark mode

```css
@media (prefers-color-scheme: dark) {
    .example-widget {
        --example-widget-border: var(--color-gray-800);
    }
}
```

> [!NOTE]
> The media query and a `.dark` ancestor are both honoured, so the component
> follows whichever mechanism the application uses.
