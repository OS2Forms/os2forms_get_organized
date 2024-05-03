<?php

namespace Drupal\os2forms_get_organized\Drush\Commands;

use Drupal\os2forms_get_organized\Helper\ArchiveHelper;
use Drush\Commands\DrushCommands;

/**
 * Test commands for get organized.
 */
class GetOrganizedTestCommands extends DrushCommands {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly ArchiveHelper $helper,
  ) {
  }

  /**
   * Test API access.
   *
   * @command os2forms-get-organized:test:api
   * @usage os2forms-get-organized:test:api --help
   */
  public function testApi(): void {
    try {
      $this->helper->pingApi();
      $this->io()->success('Successfully connected to Get Organized API');
    }
    catch (\Throwable $t) {
      $this->io()->error($t->getMessage());
    }

  }

}
