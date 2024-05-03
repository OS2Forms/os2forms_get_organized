<?php

namespace Drupal\os2forms_get_organized\Helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\key\KeyRepositoryInterface;
use Drupal\os2forms_get_organized\Form\SettingsForm;

/**
 * General settings for os2forms_get_organized.
 */
class Settings {
  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * The constructor.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly KeyRepositoryInterface $keyRepository,
  ) {
    $this->config = $configFactory->get(SettingsForm::CONFIG_NAME);
  }

  /**
   * Get key.
   */
  public function getKey(): ?string {
    return $this->get(SettingsForm::KEY);
  }

  /**
   * Get password.
   *
   * @return string
   *   The sources.
   */
  public function getBaseUrl(): string {
    $value = $this->get(SettingsForm::GET_ORGANIZED_BASE_URL);
    return is_string($value) ? $value : '';
  }

  /**
   * Get username.
   *
   * @return string
   *   The username.
   */
  public function getUsername(): string {
    return $this->getKeyValue('username');
  }

  /**
   * Get password.
   *
   * @return string
   *   The password.
   */
  public function getPassword(): string {
    return $this->getKeyValue('password');
  }

  /**
   * Get key value.
   *
   * @param string $name
   *   The value name.
   *
   * @return null|string
   *   The value if any.
   */
  private function getKeyValue(string $name): ?string {
    $key = $this->keyRepository->getKey(
      $this->getKey()
    );

    try {
      $values = json_decode($key?->getKeyValue() ?? '{}', TRUE, 512, JSON_THROW_ON_ERROR);

      return $values[$name] ?? NULL;
    }
    catch (\Throwable $exception) {
      return NULL;
    }
  }

  /**
   * Get certificate.
   */
  public function getCertificate(): ?string {
    $key = $this->keyRepository->getKey(
      $this->getKey(),
    );

    return $key?->getKeyValue();
  }

  /**
   * Get a setting value.
   *
   * @param string $key
   *   The key.
   * @param mixed|null $default
   *   The default value.
   *
   * @return mixed
   *   The setting value.
   */
  private function get(string $key, mixed $default = NULL): mixed {
    return $this->config->get($key) ?? $default;
  }

}
