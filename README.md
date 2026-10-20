# OS2Forms GetOrganized

Adds [GetOrganized](https://www.getorganized.net/) handler for archiving purposes.

## Installation

```sh
composer require os2forms/os2forms_get_organized
vendor/bin/drush pm:enable os2forms_get_organized
```

## Settings

Go to `/admin/os2forms_get_organized/settings` and configure the module.

## Development

We use [DDEV Drupal Contrib](https://github.com/ddev/ddev-drupal-contrib) for development[^1].

[^1]: The DDEV environment has been created by running

      ```shell
      ddev config --project-type=drupal11 --docroot=web
      ddev dotenv set .ddev/.env.web --drupal-core '^11.4.10'
      ```

Start the show by running

```shell
task ddev:start
```

Run `task` to see other useful development tasks including coding standards and checks.
