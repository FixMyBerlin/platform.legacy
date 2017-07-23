<?php

namespace Drupal\rate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to return permissions based on entity type for rate module.
 *
 * @package Drupal\rate
 */
class RatePermissions implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The config factory wrapper to fetch settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs Permissions object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->config = $config_factory->get('rate.settings');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get permissions for Rate module.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {
    $permissions = [];
    $enabled_types_bundles = $this->config->get('enabled_types_bundles');
    if (!empty($enabled_types_bundles)) {
      foreach ($enabled_types_bundles as $entity_type_id => $bundles) {
        foreach ($bundles as $bundle_id) {
          if ($bundle_id == $entity_type_id) {
            $perm_index = 'cast rate vote on ' . $entity_type_id . ' of ' . $bundle_id;
            $permissions[$perm_index] = [
              'title' => $this->t('Can vote on :type',
                [
                  ':type' => $entity_type_id,
                ]
              ),
            ];
          }
          else {
            $perm_index = 'cast rate vote on ' . $entity_type_id . ' of ' . $bundle_id;
            $permissions[$perm_index] = [
              'title' => $this->t('Can vote on :type type of :bundle',
                [
                  ':bundle' => $bundle_id,
                  ':type' => $entity_type_id,
                ]
              ),
            ];
          }
        }
      }
    }

    return $permissions;
  }

}
