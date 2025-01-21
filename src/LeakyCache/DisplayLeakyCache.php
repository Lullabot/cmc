<?php

namespace Drupal\cmc\LeakyCache;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

class DisplayLeakyCache implements LeakyCacheInterface{

  /**
   * {@inheritdoc}
   */
  public function processLeaks(array $leaks, Response $response): void {
    $html = $this->generateHtmlErrorMessage($leaks, $response);
    if (!empty($html)) {
      $response->setContent($html);
    }
  }

  /**
   * Prepend a message to the response's markup indicating missing cache tags.
   *
   * @param array $leaks
   * *   The missing cache tags.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response object.
   *
   * @return string
   *   The full response's content, with the missing cache tags prepended.
   */
  private function generateHtmlErrorMessage(array $leaks, Response $response): string {
    $html = '';
    $crawler = new Crawler($response->getContent());
    $body = $crawler->filterXPath('//body');
    if ($body->count() > 0) {
      $tags_markup = implode('</pre></li><li><pre>', $leaks);
      $errors = <<<MARKUP
<div id="cmc-errors">
  <h2>The following cache tags were not applied to the page:</h2>
  <ol>
    <li><pre>
    {$tags_markup}
    </pre></li>
  </ol>
</div>
MARKUP;
      $html = preg_replace(
        '/<body([^>]*)>/i',
        '<body$1>' . $errors,
        $response->getContent()
      );
    }
    return $html;
  }

}
