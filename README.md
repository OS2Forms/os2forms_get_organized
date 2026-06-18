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
# Update to make sure that we use the latest versions of tools.
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer update
# Fix (some) coding standards issues
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer coding-standards-apply
# Check that code adheres to the coding standards
docker run --rm --volume ${PWD}:/app --workdir /app itkdev/php8.3-fpm composer coding-standards-check
```

### Markdown

```shell
docker pull peterdavehello/markdownlint
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md' --fix
docker run --rm --volume $PWD:/md peterdavehello/markdownlint markdownlint --ignore vendor --ignore LICENSE.md '**/*.md'
```

## Code analysis

We use [PHPStan](https://phpstan.org/) for static code analysis.

Running static code analysis on a standalone Drupal module is a bit tricky, so we advise
running it via your main OS2Forms Drupal project.

To do so, copy the entire module folder into `web/sites/default/modules`, clear the cache and run:

```shell
docker compose exec phpfpm php vendor/bin/phpstan --configuration=web/sites/default/modules/os2forms_get_organized/phpstan.neon
```

Assuming your php container is named `phpfpm`.

