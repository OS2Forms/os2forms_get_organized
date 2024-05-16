<?php

namespace Drupal\os2forms_get_organized\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\os2forms_get_organized\Helper\ArchiveHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * GetOrganized settings form.
 */
class SettingsForm extends ConfigFormBase {
  use StringTranslationTrait;

  public const CONFIG_NAME = 'os2forms_get_organized.settings';

  public const GET_ORGANIZED_BASE_URL = 'get_organized_base_url';
  public const KEY = 'key';

  public const ACTION_PING_API = 'action_ping_api';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    private readonly ArchiveHelper $helper,
  ) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return self
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get(ArchiveHelper::class)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string>
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
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
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::CONFIG_NAME);

    $form[self::KEY] = [
      '#type' => 'key_select',
      '#key_filters' => [
        'type' => 'user_password',
      ],
      '#title' => $this->t('Key'),
      '#default_value' => $config->get(self::KEY),
    ];

    $form[self::GET_ORGANIZED_BASE_URL] = [
      '#type' => 'textfield',
      '#title' => $this->t('GetOrganized base url'),
      '#required' => TRUE,
      '#default_value' => $config->get(self::GET_ORGANIZED_BASE_URL),
      '#description' => $this->t('GetOrganized base url. Example: "https://ad.go.aarhuskommune.dk/_goapi"'),
    ];

    $form['actions']['ping_api'] = [
      '#type' => 'container',

      self::ACTION_PING_API => [
        '#type' => 'submit',
        '#name' => self::ACTION_PING_API,
        '#value' => $this->t('Ping API'),
      ],

      'message' => [
        '#markup' => $this->t('Note: Pinging the API will use saved config.'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (self::ACTION_PING_API === ($form_state->getTriggeringElement()['#name'] ?? NULL)) {
      return;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (self::ACTION_PING_API === ($form_state->getTriggeringElement()['#name'] ?? NULL)) {
      try {
        $this->helper->pingApi();
        $this->messenger()->addStatus($this->t('Pinged API successfully.'));
      }
      catch (\Throwable $t) {
        $this->messenger()->addError($this->t('Pinging API failed: @message', ['@message' => $t->getMessage()]));
      }
      return;
    }

    $config = $this->config(self::CONFIG_NAME);
    foreach ([
      self::KEY,
      self::GET_ORGANIZED_BASE_URL,
    ] as $key) {
      $config->set($key, $form_state->getValue($key));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
