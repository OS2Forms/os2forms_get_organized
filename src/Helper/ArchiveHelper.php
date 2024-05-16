<?php

namespace Drupal\os2forms_get_organized\Helper;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\os2forms_attachment\Element\AttachmentElement;
use Drupal\os2forms_get_organized\Exception\ArchivingMethodException;
use Drupal\os2forms_get_organized\Exception\CitizenArchivingException;
use Drupal\os2forms_get_organized\Exception\GetOrganizedCaseIdException;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform_attachment\Element\WebformAttachmentBase;
use ItkDev\GetOrganized\Client;
use ItkDev\GetOrganized\Service\Cases;
use ItkDev\GetOrganized\Service\Documents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Helper for archiving documents in GetOrganized.
 */
class ArchiveHelper {

  const CITIZEN_CASE_TYPE_PREFIX = 'BOR';

  /**
   * The GetOrganized Client.
   *
   * @var \ItkDev\GetOrganized\Client|null
   */
  private ?Client $client = NULL;

  /**
   * The GetOrganized Documents Service.
   *
   * @var \ItkDev\GetOrganized\Service\Documents|null
   */
  private ?Documents $documentService = NULL;

  /**
   * The GetOrganized Cases Service.
   *
   * @var \ItkDev\GetOrganized\Service\Cases|null
   */
  private ?Cases $caseService = NULL;

  /**
   * The EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The EventDispatcherInterface.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * The settings.
   *
   * @var \Drupal\os2forms_get_organized\Helper\Settings
   */
  private Settings $settings;

  /**
   * File element types.
   */
  private const FILE_ELEMENT_TYPES = [
    'webform_image_file',
    'webform_document_file',
    'webform_video_file',
    'webform_audio_file',
    'managed_file',
  ];

  /**
   * Constructs an ArchiveHelper object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher, Settings $settings) {
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->settings = $settings;
  }

  /**
   * Adds document to GetOrganized case.
   *
   * @phpstan-param array<string, mixed> $handlerConfiguration
   */
  public function archive(string $submissionId, array $handlerConfiguration): void {
    // Setup Client and services.
    if (NULL === $this->client) {
      $this->setupClient();
    }

    if (NULL === $this->caseService) {
      /** @var \ItkDev\GetOrganized\Service\Cases $caseService */
      $caseService = $this->client->api('cases');
      $this->caseService = $caseService;
    }

    if (NULL === $this->documentService) {
      /** @var \ItkDev\GetOrganized\Service\Documents $docService */
      $docService = $this->client->api('documents');
      $this->documentService = $docService;
    }

    // Detect which archiving method is required.
    $archivingMethod = $handlerConfiguration['choose_archiving_method']['archiving_method'];

    if ('archive_to_case_id' === $archivingMethod) {
      $this->archiveToCaseId($submissionId, $handlerConfiguration);
    }
    elseif ('archive_to_citizen' === $archivingMethod) {
      $this->archiveToCitizen($submissionId, $handlerConfiguration);
    }

  }

  /**
   * Sets up Client.
   */
  private function setupClient(): void {
    $username = $this->settings->getUsername();
    $password = $this->settings->getPassword();
    $baseUrl = $this->settings->getBaseUrl();

    $this->client = new Client($username, $password, $baseUrl);
  }

  /**
   * Gets WebformSubmission from id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getSubmission(string $submissionId): EntityInterface {
    $storage = $this->entityTypeManager->getStorage('webform_submission');
    return $storage->load($submissionId);
  }

  /**
   * Archives document to GetOrganized case id.
   *
   * @phpstan-param array<string, mixed> $handlerConfiguration
   */
  private function archiveToCaseId(string $submissionId, array $handlerConfiguration): void {

    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    $submission = $this->getSubmission($submissionId);

    $getOrganizedCaseId = $handlerConfiguration['choose_archiving_method']['case_id'];
    $webformAttachmentElementId = $handlerConfiguration['general']['attachment_element'];
    $shouldBeFinalized = $handlerConfiguration['general']['should_be_finalized'] ?? FALSE;
    $shouldArchiveFiles = $handlerConfiguration['general']['should_archive_files'] ?? FALSE;

    // Ensure case id exists.
    $case = $this->caseService->getByCaseId($getOrganizedCaseId);

    if (!$case) {
      $message = sprintf('Could not find a case with id %s.', $getOrganizedCaseId);
      throw new GetOrganizedCaseIdException($message);
    }

    $this->uploadDocumentToCase($getOrganizedCaseId, $webformAttachmentElementId, $submission, $shouldArchiveFiles, $shouldBeFinalized);
  }

  /**
   * Archives document to GetOrganized citizen subcase.
   *
   * @phpstan-param array<string, mixed> $handlerConfiguration
   */
  private function archiveToCitizen(string $submissionId, array $handlerConfiguration): void {
    // Step 1: Find/create parent case
    // Step 2: Find/create subcase
    // Step 3: Upload to subcase.
    if (NULL === $this->client) {
      $this->setupClient();
    }

    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    $submission = $this->getSubmission($submissionId);

    $cprValueElementId = $handlerConfiguration['choose_archiving_method']['cpr_value_element'];
    $cprElementValue = $submission->getData()[$cprValueElementId];

    $cprNameElementId = $handlerConfiguration['choose_archiving_method']['cpr_name_element'];
    $cprNameElementValue = $submission->getData()[$cprNameElementId];

    // Step 1: Find/create parent case.
    $caseQuery = [
      'FieldProperties' => [
           [
             'InternalName' => 'ows_CCMContactData_CPR',
             'Value' => $cprElementValue,
           ],
      ],
      'CaseTypePrefixes' => [
        self::CITIZEN_CASE_TYPE_PREFIX,
      ],
      'LogicalOperator' => 'AND',
      'ExcludeDeletedCases' => TRUE,
      'ReturnCasesNumber' => 25,
    ];

    $caseResult = $this->caseService->FindByCaseProperties(
      $caseQuery
    );

    // Subcases may also contain the 'ows_CCMContactData_CPR' property,
    // i.e. we need to check result cases are not subcases.
    // $caseResult will always contain the 'CasesInfo' key,
    // and its value will always be an array.
    $caseInfo = array_filter($caseResult['CasesInfo'], function ($caseInfo) {
      // Parent cases are always on the form AAA-XXXX-XXXXXX,
      // Subcases are always on the form AAA-XXXX-XXXXXX-XXX,
      // I.e. we can filter out subcases by checking number of dashes in id.
      return 2 === substr_count($caseInfo['CaseID'], '-');
    });

    $parentCaseCount = count($caseInfo);

    if (0 === $parentCaseCount) {
      $parentCaseId = $this->createCitizenCase($cprElementValue, $cprNameElementValue);
    }
    elseif (1 < $parentCaseCount) {
      $message = sprintf('Too many (%d) parent cases.', $parentCaseCount);
      throw new CitizenArchivingException($message);
    }
    else {
      $parentCaseId = $caseResult['CasesInfo'][0]['CaseID'];
    }

    // Step 2: Find/create subcase.
    $subcaseName = $handlerConfiguration['choose_archiving_method']['sub_case_title'];

    $subCasesQuery = [
      'FieldProperties' => [
        [
          'InternalName' => 'ows_CaseId',
          'Value' => $parentCaseId . '-',
          'ComparisonType' => 'Contains',
        ],
        [
          'InternalName' => 'ows_Title',
          'Value' => $subcaseName,
          'ComparisonType' => 'Equal',
        ],
      ],
      'CaseTypePrefixes' => [
        self::CITIZEN_CASE_TYPE_PREFIX,
      ],
      'LogicalOperator' => 'AND',
      'ExcludeDeletedCases' => TRUE,
      // Unsure how many subcases may exist, but fetching 25 should be enough.
      'ReturnCasesNumber' => 25,
    ];

    $subCases = $this->caseService->FindByCaseProperties(
      $subCasesQuery
    );

    $subCaseCount = count($subCases['CasesInfo']);

    if (0 === $subCaseCount) {
      $subCaseId = $this->createSubCase($parentCaseId, $subcaseName);
    }
    elseif (1 === $subCaseCount) {
      $subCaseId = $subCases['CasesInfo'][0]['CaseID'];
    }
    else {
      $message = sprintf('Too many (%d) subcases with the name %s', $subCaseCount, $subcaseName);
      throw new CitizenArchivingException($message);
    }

    // Step 3: Upload to subcase.
    $webformAttachmentElementId = $handlerConfiguration['general']['attachment_element'];
    $shouldBeFinalized = $handlerConfiguration['general']['should_be_finalized'] ?? FALSE;
    $shouldArchiveFiles = $handlerConfiguration['general']['should_archive_files'] ?? FALSE;

    $this->uploadDocumentToCase($subCaseId, $webformAttachmentElementId, $submission, $shouldArchiveFiles, $shouldBeFinalized);
  }

  /**
   * Creates citizen parent case in GetOrganized.
   */
  private function createCitizenCase(string $cprElementValue, string $cprNameElementValue): string {

    $metadataArray = [
      'ows_Title' => $cprElementValue . ' - ' . $cprNameElementValue,
      // CCMContactData format: 'name;#ID;#CRP;#CVR;#PNumber',
      // We don't create GetOrganized parties (parter) so we leave that empty
      // We also don't use cvr- or p-number so we leave those empty.
      'ows_CCMContactData' => $cprNameElementValue . ';#;#' . $cprElementValue . ';#;#',
      'ows_CaseStatus' => 'Åben',
    ];

    $response = $this->caseService->createCase(self::CITIZEN_CASE_TYPE_PREFIX, $metadataArray);

    if (empty($response)) {
      throw new CitizenArchivingException('Could not create citizen case');
    }
    // Example response.
    // {"CaseID":"BOR-2022-000046","CaseRelativeUrl":"\/cases\/BOR12\/BOR-2022-000046",...}.
    return $response['CaseID'];
  }

  /**
   * Creates citizen subcase in GetOrganized.
   */
  private function createSubCase(string $caseId, string $caseName): string {
    $metadataArray = [
      'ows_Title' => $caseName,
      'ows_CCMParentCase' => $caseId,
      // For creating subcases the 'ows_ContentTypeId' must be set explicitly to
      // '0x0100512AABDB08FA4fadB4A10948B5A56C7C01'.
      'ows_ContentTypeId' => '0x0100512AABDB08FA4fadB4A10948B5A56C7C01',
      'ows_CaseStatus' => 'Åben',
    ];

    $response = $this->caseService->createCase(self::CITIZEN_CASE_TYPE_PREFIX, $metadataArray);

    // Example response.
    // {"CaseID":"BOR-2022-000046-001","CaseRelativeUrl":"\/cases\/BOR12\/BOR-2022-000046",...}.
    return $response['CaseID'];
  }

  /**
   * Uploads attachment document and attached files to GetOrganized case.
   */
  private function uploadDocumentToCase(string $caseId, string $webformAttachmentElementId, WebformSubmission $submission, bool $shouldArchiveFiles, bool $shouldBeFinalized): void {
    // Handle main document (the attachment).
    $webformAttachmentElement = $submission->getWebform()->getElement($webformAttachmentElementId);
    $fileContent = AttachmentElement::getFileContent($webformAttachmentElement, $submission);
    $webformLabel = $submission->getWebform()->label();
    $webformLabel = str_replace('/', '-', $webformLabel);
    $pdfExtension = '.pdf';

    if (isset($webformAttachmentElement['#filename'])) {
      // Computes webform attachment's file name.
      $baseName = WebformAttachmentBase::getFileName($webformAttachmentElement, $submission);

      $getOrganizedFilename = $this->computeGetOrganizedFilename($baseName, $submission);
    }
    else {
      $getOrganizedFilename = $webformLabel . '-' . $submission->serial() . $pdfExtension;
    }

    // Ids that should possibly be finalized (journaliseret) later.
    $documentIdsForFinalizing = [];

    $getOrganizedFilename = $this->sanitizeFilename($getOrganizedFilename);

    $parentDocumentId = $this->archiveDocumentToGetOrganizedCase($caseId, $getOrganizedFilename, $fileContent);

    $documentIdsForFinalizing[] = $parentDocumentId;

    // Handle attached files.
    if ($shouldArchiveFiles) {
      $fileIds = $this->getFileElementKeysFromSubmission($submission);

      $childDocumentIds = [];

      $fileStorage = $this->entityTypeManager->getStorage('file');

      foreach ($fileIds as $fileId) {
        /** @var \Drupal\file\Entity\File $file */
        $file = $fileStorage->load($fileId);
        $filename = $file->getFilename();
        $getOrganizedFilename = $this->computeGetOrganizedFilename($filename, $submission);
        $getOrganizedFilename = $this->sanitizeFilename($getOrganizedFilename);

        $fileContent = file_get_contents($file->getFileUri());

        $childDocumentId = $this->archiveDocumentToGetOrganizedCase($caseId, $getOrganizedFilename, $fileContent);

        $childDocumentIds[] = $childDocumentId;
      }

      $documentIdsForFinalizing = array_merge($documentIdsForFinalizing, $childDocumentIds);

      $this->documentService->RelateDocuments($parentDocumentId, $childDocumentIds, 1);
    }

    if ($shouldBeFinalized) {
      $this->documentService->FinalizeMultiple($documentIdsForFinalizing);
    }
  }

  /**
   * Get available elements by type.
   *
   * @phpstan-param array<string, mixed> $elements
   * @phpstan-return array<string, mixed>
   */
  private function getAvailableElementsByType(string $type, array $elements): array {
    $attachmentElements = array_filter($elements, function ($element) use ($type) {
      return $type === $element['#type'];
    });

    return array_map(function ($element) {
      return $element['#title'];
    }, $attachmentElements);
  }

  /**
   * Archives file content to GetOrganized case.
   */
  private function archiveDocumentToGetOrganizedCase(string $caseId, string $getOrganizedFileName, string $fileContent): int {
    $tempFile = tempnam('/tmp', $caseId . '-' . uniqid());

    try {
      file_put_contents($tempFile, $fileContent);

      $result = $this->documentService->AddToDocumentLibrary($tempFile, $caseId, $getOrganizedFileName);

      if (!isset($result['DocId'])) {
        throw new ArchivingMethodException('Could not get document id from response.');
      }

      $documentId = $result['DocId'];
    } finally {
      // Remove temp file.
      unlink($tempFile);
    }

    return (int) $documentId;
  }

  /**
   * Returns array of file elements keys in submission.
   *
   * @phpstan-return array<string, mixed>
   */
  private function getFileElementKeysFromSubmission(WebformSubmission $submission): array {
    $elements = $submission->getWebform()->getElementsDecodedAndFlattened();

    $fileElements = [];

    foreach (self::FILE_ELEMENT_TYPES as $fileElementType) {
      $fileElements[] = $this->getAvailableElementsByType($fileElementType, $elements);
    }

    // https://dev.to/klnjmm/never-use-arraymerge-in-a-for-loop-in-php-5go1
    $fileElements = array_merge(...$fileElements);

    $elementKeys = array_keys($fileElements);

    $fileIds = [];

    foreach ($elementKeys as $elementKey) {
      if (empty($submission->getData()[$elementKey])) {
        continue;
      }

      // Convert occurrences of singular file into array.
      $elementFileIds = (array) $submission->getData()[$elementKey];

      $fileIds[] = $elementFileIds;
    }

    return array_merge(...$fileIds);
  }

  /**
   * Convert filename into GetOrganized filename.
   *
   * Adds webform label and submission number before its file extension.
   *
   * Example:
   *
   * Input: SomeFilename.pdf
   * Output: SomeFilename-[FORMULAR_LABEL]-[SUBMISSION_NUMBER].pdf
   */
  private function computeGetOrganizedFilename(string $filename, WebformSubmission $submission): string {
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $webformLabel = $submission->getWebform()->label();

    // Remove non-allowed filename characters from webform label.
    $nonAllowedCharacters = [
      '\\',
      '/',
      ':',
      '*',
      '?',
      '"',
      '<',
      '>',
      '|',
    ];

    foreach ($nonAllowedCharacters as $character) {
      $webformLabel = str_replace($character, '-', $webformLabel);
    }

    $submissionNumber = $submission->serial();

    // Find position of last occurrence of extension.
    $position = strrpos($filename, '.' . $fileExtension);

    // Inject the webform label and submission number at found position.
    return substr_replace($filename, '-' . $webformLabel . '-' . $submissionNumber, $position, 0);
  }

  /**
   * Sanitizes filename.
   */
  private function sanitizeFilename(string $filename): string {
    // @see https://www.drupal.org/node/3032541
    // We just want to sanitize filename,
    // hence the empty string in allowed_extensions.
    $event = new FileUploadSanitizeNameEvent($filename, '');

    $this->eventDispatcher->dispatch($event);

    return $event->getFilename();
  }

}
