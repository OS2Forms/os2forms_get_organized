<?php

namespace Drupal\os2forms_get_organized\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\os2forms_get_organized\Helper\Settings;
use ItkDev\GetOrganized\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;

/**
 * GetOrganized settings form.
 */
class SettingsForm extends FormBase {
  use StringTranslationTrait;

  public const GET_ORGANIZED_USERNAME = 'get_organized_username';
  public const GET_ORGANIZED_PASSWORD = 'get_organized_password';
  public const GET_ORGANIZED_BASE_URL = 'get_organized_base_url';

  /**
   * The settings.
   *
   * @var \Drupal\os2forms_get_organized\Helper\Settings
   */
  private Settings $settings;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SettingsForm {
    return new static(
      $container->get(Settings::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'os2forms_get_organized_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form[self::GET_ORGANIZED_USERNAME] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#default_value' => $this->settings->getUsername(),
    ];

    $form[self::GET_ORGANIZED_PASSWORD] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
      '#default_value' => $this->settings->getPassword(),
    ];

    $form[self::GET_ORGANIZED_BASE_URL] = [
      '#type' => 'textfield',
      '#title' => $this->t('GetOrganized base url'),
      '#required' => TRUE,
      '#default_value' => $this->settings->getBaseUrl(),
      '#description' => $this->t('GetOrganized base url. Example: "https://ad.go.aarhuskommune.dk/_goapi"'),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
    ];

    $form['actions']['testSettings'] = [
      '#type' => 'submit',
      '#name' => 'testSettings',
      '#value' => $this->t('Test provided information'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $username = $formState->getValue(self::GET_ORGANIZED_USERNAME);
    $password = $formState->getValue(self::GET_ORGANIZED_PASSWORD);
    $baseUrl = $formState->getValue(self::GET_ORGANIZED_BASE_URL);

    $triggeringElement = $formState->getTriggeringElement();
    if ('testSettings' === ($triggeringElement['#name'] ?? NULL)) {
      $this->testSettings($username, $password, $baseUrl);
      return;
    }

    try {
      $settings[self::GET_ORGANIZED_USERNAME] = $username;
      $settings[self::GET_ORGANIZED_PASSWORD] = $password;
      $settings[self::GET_ORGANIZED_BASE_URL] = $baseUrl;

      $this->settings->setSettings($settings);
      $this->messenger()->addStatus($this->t('Settings saved'));
    }
    catch (OptionsResolverException $exception) {
      $this->messenger()->addError($this->t('Settings not saved (@message)', ['@message' => $exception->getMessage()]));
    }

    $this->messenger()->addStatus($this->t('Settings saved'));
  }

  /**
   * Test settings by making some arbitrary call to the GetOrganized API.
   */
  private function testSettings(string $username, string $password, string $baseUrl): void {
    try {
      $client = new Client($username, $password, $baseUrl);
      /** @var \ItkDev\GetOrganized\Service\Tiles $tileService */
      $tileService = $client->api('tiles');

      $result = $tileService->GetTilesNavigation();

      if (empty($result)) {
        $message = $this->t('Error occurred while testing the GetOrganized API with provided settings.');
        $this->messenger()->addError($message);
      }
      else {
        $this->messenger()->addStatus($this->t('Settings succesfully tested'));
      }
    }
    catch (\Throwable $throwable) {
      $message = $this->t('Error testing provided information: %message', ['%message' => $throwable->getMessage()]);
      $this->messenger()->addError($message);
    }
  }

}
