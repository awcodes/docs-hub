# Development

## Getting the code

```shell
git clone git@github.com:acme/example.git
cd example
composer install
```

## Working against an application

Require the package from a path repository so edits show up immediately:

```json
{
    "repositories": [
        { "type": "path", "url": "../example" }
    ]
}
```

## Coding style

Run Pint before committing. The CI job fails on any difference.

```shell
composer lint
```

Then run the suite described in [Testing](testing.md).
