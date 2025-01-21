<?php

declare(strict_types=1);

namespace Drupal\cmc\EventSubscriber;

use Drupal\cmc\EntityCacheTagCollector;
use Drupal\cmc\Exception\MissingCacheTagsException;
use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tracks cache tags from entities used for the current request.
 */
class LoadedEntitySubscriber implements EventSubscriberInterface {

  /**
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match object.
   */
  public function __construct(
    protected readonly EntityCacheTagCollector $entityCacheTagCollector,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly RequestStack $requestStack,
    protected readonly RouteMatchInterface $routeMatch
  ) {
    $this->config = $this->configFactory->get('cmc.settings');
  }

  /**
   * Acts on the response about to be returned.
   *
   * Will check the cache tags from the response against the cache tags
   * collected during all entity loads of this request. If there are
   * differences, will either display an error message or fail hard, indicating
   * in both cases the missing tags.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $responseEvent
   *   The response event.
   *
   * @return void
   *
   * @throws \Drupal\cmc\Exception\MissingCacheTagsException
   */
  public function onResponse(ResponseEvent $responseEvent): void {
    // Nothing to do if admins disabled this module.
    $operation_mode = $this->config->get('operation_mode');
    if ($operation_mode === 'disabled') {
      return;
    }

    if ($this->shouldSkipAdminCheck()) {
      return;
    }

    // Abort if this response does not contain cache metadata.
    $response = $responseEvent->getResponse();
    if (!($response instanceof CacheableResponseInterface)) {
      return;
    }

    // Return early if the current path is flagged as skipped.
    $current_request = $this->requestStack->getCurrentRequest();
    if ($this->isSkippedUrl($current_request->getPathInfo())) {
      return;
    }

    // Never fail hard on our own config page to avoid smart users locking
    // themselves out of the house.
    if (
      $operation_mode === 'strict'
      && $this->routeMatch->getRouteName() === 'cmc.settings'
    ) {
      return;
    }

    $diff = array_diff(
      $this->entityCacheTagCollector->getTagsFromLoadedEntities(),
      $response->getCacheableMetadata()->getCacheTags(),
    );
    if (!empty($diff)) {
      if ($operation_mode === 'errors') {
        $html = $this->generateHtmlErrorMessage($response, $diff);
        if (!empty($html)) {
          $response->setContent($html);
        }
      }
      elseif ($operation_mode === 'strict') {
        throw new MissingCacheTagsException("The following cache tags were not applied to the page: " . implode(", ", $diff));
      }
    }
  }

  /**
   * Determines if the admin page check should be skipped.
   *
   * @return bool
   *   TRUE if the check should be skipped, FALSE otherwise.
   */
  private function shouldSkipAdminCheck(): bool {
    $isAdminCheckSkipped = $this->config->get('skip_admin') ?? TRUE;
    if (!$isAdminCheckSkipped) {
      return FALSE;
    }

    $currentTheme = $this->themeManager->getActiveTheme()->getName();
    $adminTheme = $this->configFactory->get('system.theme')->get('admin');

    return $currentTheme === $adminTheme;
  }

  /**
   * Checks if the given path is in the list of skipped URLs.
   *
   * @param string $currentPath
   *   The current request path.
   *
   * @return bool
   *   TRUE if the URL should be skipped, FALSE otherwise.
   */
  private function isSkippedUrl(string $currentPath): bool {
    $skippedUrls = $this->config->get('skip_urls') ?? [];
    return in_array($currentPath, $skippedUrls, true);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  /**
   * Prepend a message to the response's markup indicating missing cache tags.
   *
   * @param \Drupal\Core\Cache\CacheableResponseInterface $response
   *   The response object.
   * @param array $diff
   *   The missing cache tags.
   *
   * @return string
   *   The full response's content, with the missing cache tags prepended.
   */
  private function generateHtmlErrorMessage(CacheableResponseInterface $response, array $diff): string {
    $html = '';
    $crawler = new Crawler($response->getContent());
    $body = $crawler->filterXPath('//body');
    if ($body->count() > 0) {
      $tags_markup = implode('</pre></li><li><pre>', $diff);
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
