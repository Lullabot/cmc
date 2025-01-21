<?php

namespace Drupal\cmc\LeakyCache;

use Symfony\Component\HttpFoundation\Response;

class NullLeakyCache implements LeakyCacheInterface {

  /**
   * {@inheritdoc}
   */
  public function processLeaks(array $leaks, Response $response): void {
  }

}
