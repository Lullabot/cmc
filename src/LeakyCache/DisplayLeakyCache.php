<?php

declare(strict_types=1);

namespace Drupal\cmc\LeakyCache;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\Response;

class DisplayLeakyCache implements LeakyCacheInterface {

  public function __construct(
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processLeaks(array $leaks, Response $response): void {
    $tags_items = array_map(
      fn(string $tag) => '<code>' . htmlspecialchars($tag) . '</code>',
      $leaks
    );
    $tags_list = '<ul><li>' . implode('</li><li>', $tags_items) . '</li></ul>';

    $message = Markup::create(
      '<strong>Some cache tags are missing</strong>' .
      '<p>The following entity cache tags are missing from the response:</p>' .
      $tags_list
    );

    $this->messenger->addError($message);
  }

}
