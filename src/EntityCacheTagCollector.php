<?php

namespace Drupal\cmc;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandler;

class EntityCacheTagCollector {

  /**
   * The cache tags for all entities being tracked on a given request.
   *
   * @var array
   */
  private array $tagsFromLoadedEntities = [];

  public function __construct(private readonly ModuleHandler $moduleHandler) {}

  /**
   * Registers cache tags for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being tracked.
   */
  public function registerLoadedEntity(EntityInterface $entity): void {
    if (!$this->shouldTrack($entity)) {
      return;
    }
    $this->tagsFromLoadedEntities = array_unique([
      ...$this->tagsFromLoadedEntities,
      ...$entity->getCacheTags(),
    ]);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   *   TRUE if this entity should be tracked, FALSE otherwise.
   */
  private function shouldTrack(EntityInterface $entity): bool {
    // Allow modules to modify this.
    $skip = $this->moduleHandler->invokeAll('cmc_skip_tracking', [$entity]);
    // If at least one module wants to skip the tracking, bail out.
    if (in_array(TRUE, $skip, TRUE)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Retrieves the tags from the loaded entities.
   *
   * @return array
   *   An array of tags associated with the loaded entities.
   */
  public function getTagsFromLoadedEntities(): array {
    return $this->tagsFromLoadedEntities;
  }

}
