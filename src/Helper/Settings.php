<?php

namespace Drupal\os2forms_get_organized\Helper;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\os2forms_get_organized\Exception\InvalidSettingException;
use Drupal\os2forms_get_organized\Form\SettingsForm;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * General settings for os2forms_get_organized.
 */
final class Settings {
  /**
   * The store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private KeyValueStoreInterface $store;

  /**
   * The key value collection name.
   *
   * @var string
   */
  private $collection = 'os2forms_get_organized';

  /**
   * Default setting values.
   *
   * @var array
   * @phpstan-var array<string, string>
   */
  private $defaultSettings = [
    SettingsForm::GET_ORGANIZED_USERNAME => '',
    SettingsForm::GET_ORGANIZED_PASSWORD => '',
    SettingsForm::GET_ORGANIZED_BASE_URL => '',
  ];

  /**
   * Constructor.
   */
  public function __construct(KeyValueFactoryInterface $keyValueFactory) {
    $this->store = $keyValueFactory->get($this->collection);
  }

  /**
   * Get username.
   *
   * @return string
   *   The sources.
   */
  public function getUsername(): string {
    $value = $this->get(SettingsForm::GET_ORGANIZED_USERNAME);
    return is_string($value) ? $value : '';
  }

  /**
   * Get password.
   *
   * @return string
   *   The sources.
   */
  public function getPassword(): string {
    $value = $this->get(SettingsForm::GET_ORGANIZED_PASSWORD);
    return is_string($value) ? $value : '';
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
    $resolver = $this->getSettingsResolver();
    if (!$resolver->isDefined($key)) {
      throw new InvalidSettingException(sprintf('Setting %s is not defined', $key));
    }

    return $this->store->get($key, $default);
  }

  /**
   * Set settings.
   *
   * @throws \Symfony\Component\OptionsResolver\Exception\ExceptionInterface
   *
   * @phpstan-param array<string, mixed> $settings
   */
  public function setSettings(array $settings): self {
    $settings = $this->getSettingsResolver()->resolve($settings);
    foreach ($settings as $key => $value) {
      $this->store->set($key, $value);
    }

    return $this;
  }

  /**
   * Get settings resolver.
   */
  private function getSettingsResolver(): OptionsResolver {
    return (new OptionsResolver())
      ->setDefaults($this->defaultSettings)
      ->setAllowedTypes(SettingsForm::GET_ORGANIZED_USERNAME, 'string')
      ->setAllowedTypes(SettingsForm::GET_ORGANIZED_PASSWORD, 'string')
      ->setAllowedTypes(SettingsForm::GET_ORGANIZED_BASE_URL, 'string')
      ->setRequired([
        SettingsForm::GET_ORGANIZED_USERNAME,
        SettingsForm::GET_ORGANIZED_PASSWORD,
        SettingsForm::GET_ORGANIZED_BASE_URL,
      ]);
  }

}
