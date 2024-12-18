<?php

namespace Drupal\os2forms_get_organized\Commands;

use Drupal\os2forms_get_organized\Helper\ArchiveHelper;
use Drupal\webform\Entity\WebformSubmission;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drush command file.
 */
class ArchiveCommands extends DrushCommands {

  public function __construct(
    private readonly ArchiveHelper $archiveHelper,
  ) {
    parent::__construct();
  }

  /**
   * A custom Drush command to displays the given text.
   *
   * @param string $submissionId
   *   The submission id.
   * @param string $handlerId
   *   The handler id.
   *
   * @command os2forms:get-organized:archive
   */
  public function archive(string $submissionId, string $handlerId) {

    $io = new SymfonyStyle($this->input(), $this->output());

    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = WebformSubmission::load($submissionId);

    if (!$submission) {
      $io->error(sprintf('Webform submission with id %s could not be found.', $submissionId));

      return;
    }

    try {
      $handler = $submission->getWebform()->getHandler($handlerId);
    }
    catch (\Exception $e) {
      $io->error($e->getMessage());

      return;
    }

    $handlerConfig = $handler->getConfiguration();

    $this->archiveHelper->archive($submissionId, $handlerConfig['settings']);

    $io->success(sprintf('Successfully archived webform submission with id %s ', $submissionId));
  }

}
