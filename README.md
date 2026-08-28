# Jump Start

A custom starter kit for Laravel development, with defaults I like.

[![MIT Licensed](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE.md)

## Installation

```bash
laravel new <my-app> --using=awcodes/jump-start --no-interaction
```

After installation, cd into the project and run:

```bash
npm install && npm run build

php artisan db:seed
```

## Boost

If you need to change any of the Boost configurations, you can re-run the Boost installer with:

```bash
php artisan boost:install
```

## Includes

- Laravel 13
- Tailwind CSS
- Pint
- Pest
- Rector
- Laravel Debugbar
- Spatie Ray
- Larastan
- Laravel Boost

## Tests

There are some test commands available via composer scripts.

```bash
# Runs Pint to lint the codebase
composer lint

# Runs Rector to refactor the codebase
composer refactor

# Runs Pint in test mode to check for linting issues without fixing them
composer test:lint

# Runs Rector in dry-run mode to check for refactoring issues without applying changes
composer test:refactor

# Runs PHPStan to analyze the code for type issues
composer test:types

# Runs Pest to execute unit tests in parallel
composer test:unit

# Runs a series of tests including config clear, Rector dry-run, Pint test, PHPStan, and Pest unit tests
composer test 
```
