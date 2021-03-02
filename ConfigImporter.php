<?php

namespace Kerasai\DrupalConfigImporter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Class ConfigImporter.
 */
class ConfigImporter {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * ConfigImporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Site\Settings $settings
   *   Drupal settings.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, Settings $settings) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->settings = $settings;
  }

  /**
   * Create a config importer from Drupal's default services.
   */
  public static function create() {
    return new static(
      \Drupal::entityTypeManager(),
      \Drupal::configFactory(),
      \Drupal::service('settings'),
    );
  }

  /**
   * Imports config or config entities from YML.
   *
   * Automatically detects if the configuration is a config entity or simple
   * configuration.
   *
   * @param string $id
   *   The config ID.
   * @param string $path
   *   The path to the config to be synced. Optional, defaults to the site's
   *   config sync directory.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The config or config entity that was imported.
   */
  public function import($id, $path = NULL) {
    if ($meta = $this->getConfigEntityMeta($id)) {
      $import = $this->importConfigEntity($id, $meta['entity_id'], $meta['entity_type'], $path);
    }
    else {
      $import = $this->importConfig($id, $path);
    }
    return $import;
  }

  /**
   * Import config from YML.
   *
   * @param string $id
   *   The config ID.
   * @param string $path
   *   The path to the config to be synced. Optional, defaults to the site's
   *   config sync directory.
   *
   * @return \Drupal\Core\Config\Config
   *   The imported config object.
   */
  public function importConfig($id, $path = NULL) {
    if (!$path) {
      $path = $this->getConfigSyncPath();
    }

    $source = new FileStorage($path);
    $data = $source->read($id);

    // Loads existing or creates new.
    $config = $this->configFactory->getEditable($id);
    $config->setData($data)->save();

    return $config;
  }

  /**
   * Import config entity from YML.
   *
   * @param string $config_id
   *   The config ID.
   * @param string $id
   *   The config entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $path
   *   The path to the config to be synced. Optional, defaults to the site's
   *   config sync directory.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The imported config entity.
   */
  public function importConfigEntity($config_id, $id, $entity_type, $path = NULL) {
    if (!$path) {
      $path = $this->getConfigSyncPath();
    }

    $source = new FileStorage($path);
    $data = $source->read($config_id);

    $storage = $this->entityTypeManager->getStorage($entity_type);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    if ($entity = $storage->load($id)) {
      foreach ($data as $key => $val) {
        $entity->set($key, $val);
      }
      // @todo unset keys not found in source?
    }
    else {
      $entity = $storage->create($data);
    }
    $entity->save();

    return $entity;
  }

  /**
   * Get the path to the configuration sync directory.
   *
   * @return string
   *   The path to the configuration sync directory.
   */
  protected function getConfigSyncPath() {
    /** @var \Drupal\Core\Site\Settings $settings */
    $settings = \Drupal::service('settings');
    return $settings->get('config_sync_directory');
  }

  /**
   * Gets meta regarding a config entity from a config ID.
   *
   * @param string $id
   *   The config ID.
   *
   * @return array|false
   *   An array describing the entity type and ID of the config, or FALSE if the
   *   config is determined to not be a config entity.
   */
  protected function getConfigEntityMeta($id) {
    $parts = explode('.', $id);
    if (count($parts) != 3) {
      return FALSE;
    }
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] $defs */
    $defs = array_filter($this->entityTypeManager->getDefinitions(), function ($def) use ($parts) {
      if ($def->getProvider() != $parts[0]) {
        return FALSE;
      }
      if ($prefix = $def->get('config_prefix')) {
        return $parts[1] == $prefix;
      }
      return $parts[1] == $def->id();
    });
    if (!$def = reset($defs)) {
      return FALSE;
    }

    return ['entity_type' => $def->id(), 'entity_id' => $parts[2]];
  }

}
