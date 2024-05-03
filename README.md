# OS2Forms GetOrganized

Adds [GetOrganized](https://www.getorganized.net/) handler for archiving purposes.

## Installation

```sh
composer require os2forms/os2forms_get_organized
vendor/bin/drush pm:enable os2forms_get_organized
```

## Settings

Go to `/admin/os2forms_get_organized/settings` and configure the module.

## Coding standards

Our coding are checked by GitHub Actions (cf. [.github/workflows/pr.yml](.github/workflows/pr.yml)). Use the commands
below to run the checks locally.

### PHP

```shell
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.1-fpm composer install
# Fix (some) coding standards issues
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.1-fpm composer coding-standards-apply
# Check that code adheres to the coding standards
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.1-fpm composer coding-standards-check
```

### Markdown

```shell
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md' --fix
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md'
```

## Code analysis

We use [PHPStan](https://phpstan.org/) for static code analysis.

Running statis code analysis on a standalone Drupal module is a bit tricky, so we use a helper script to run the
analysis:

```shell
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.1-fpm ./scripts/code-analysis
```
