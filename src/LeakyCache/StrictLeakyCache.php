<?php

namespace Drupal\cmc\LeakyCache;

use Drupal\cmc\Exception\MissingCacheTagsException;
use Symfony\Component\HttpFoundation\Response;

class StrictLeakyCache implements LeakyCacheInterface {

  /**
   * {@inheritdoc}
   */
  public function processLeaks(array $leaks, Response $response): void {
    throw new MissingCacheTagsException("The following cache tags were not applied to the page: " . implode(", ", $leaks));
  }

}
