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
<dialog id="cmc-errors" style="padding: 0; border-width: 1px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.26); border-color: rgba(0, 0, 0, 0.26)">
  <div style="display: flex; justify-content: space-between; width: 100%; background: #DDDDDD; padding: 10px; position: sticky; top: 0">
    <h3 style="margin: 0">Some cache tags are missing</h3>
    <button style="padding: 0; width: 28px; height: 28px; border-radius: 4px; border: 1px solid rgba(0, 0, 0, 0.26)" title="Close" onclick="document.getElementById('cmc-errors').close()">✕</button>
  </div>
  <div style="padding: 0 20px 20px;">
    <p>The following entity cache tags are missing from the response:</p>
    <ul>
      <li><pre>
      {$tags_markup}
      </pre></li>
    </ul>
  </div>
</dialog>
<script>document.getElementById('cmc-errors').showModal()</script>
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
