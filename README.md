# OS2Forms GetOrganized

Adds [GetOrganized](https://www.getorganized.net/) handler for archiving purposes.

## Installation

```sh
composer require os2forms/os2forms_get_organized
vendor/bin/drush pm:enable os2forms_get_organized
```

## Settings

Set GetOrganized `username`, `password` and `base url`
on `/admin/os2forms_get_organized/settings`.

You can also test that the provided
details work on `/admin/os2forms_get_organized/settings`.

## Coding standards

Check coding standards:

```sh
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer install
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer coding-standards-check
```

Apply coding standards:

```shell
docker run --rm --interactive --tty --volume ${PWD}:/app itkdev/php8.1-fpm:latest composer coding-standards-apply
docker run --rm --interactive --tty --volume ${PWD}:/app node:18 yarn --cwd /app coding-standards-apply
```
