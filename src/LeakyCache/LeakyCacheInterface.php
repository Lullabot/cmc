<?php

namespace Drupal\cmc\LeakyCache;

use Symfony\Component\HttpFoundation\Response;

interface LeakyCacheInterface {

  /**
   * Processes the given leaks and updates the response accordingly.
   *
   * @param array $leaks
   *   The array of leaks to be processed.
   * @param Response $response
   *   The response instance to be updated during processing.
   *
   * @return void
   */
  public function processLeaks(array $leaks, Response $response): void;

}
