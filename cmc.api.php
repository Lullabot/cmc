<?php

/**
 * @file
 * Hooks provided by the cmc module.
 */

declare(strict_types=1);

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Allows modules to mark an entity load to skip cache tags tracking.
 *
 * Modules implementing this hook should return TRUE if a particular entity
 * should be skipped when checking if its cache tags are present on a response.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity being checked.
 */
function hook_cmc_skip_tracking(EntityInterface $entity): bool {
  if ($entity->label() === 'This is not the entity you are looking for.') {
    return TRUE;
  }
  return FALSE;
}

/**
 * @} End of "addtogroup hooks".
 */
