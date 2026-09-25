# Boundaries

What the package owns, and what it deliberately leaves to the application.

## The package owns

- The `Widget` model and its table.
- The Filament resource and its forms.
- The Blade component and its markup.

## The application owns

- Authentication and authorization — see
  [Authentication](../usage/authentication.md).
- The panel the resource is registered on.
- Styling beyond the custom properties.

## Why the line is there

A package that reached into authentication would have to guess at every
application's user model. Asking through a gate keeps that decision where it
belongs.
