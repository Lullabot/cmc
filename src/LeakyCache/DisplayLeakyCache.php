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
<dialog open id="cmc-errors" style="display: flex; flex-direction: column; justify-content: center; position: fixed; top: 10%; transform: translateX(-10%); border: 1px solid black; border-radius: 4px; box-shadow: 0 0 5px black">
  <div style="font-size: 1.4em; font-weight: bold">Some cache tags are missing</div>
  <div>
    <p>The following entity cache tags are missing from the response:</p>
    <ul>
      <li><pre>
      {$tags_markup}
      </pre></li>
    </ul>
  </div>
  <div style="display: flex; justify-content: flex-end; width: 100%">
    <button class="button button--primary" onclick="document.getElementById('cmc-errors').close()">Close</button>
  </div>
</dialog>
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
