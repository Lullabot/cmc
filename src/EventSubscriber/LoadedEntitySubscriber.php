<?php

declare(strict_types=1);

namespace Drupal\cmc\EventSubscriber;

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
   * The cache tags for all entities being tracked on a given request.
   *
   * @var array
   */
  private $tagsFromLoadedEntities = [];

  /**
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $currentRequest;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
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
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly RequestStack $requestStack,
    protected readonly RouteMatchInterface $routeMatch
  ) {
    $this->config = $this->configFactory->get('cmc.settings');
    $this->currentRequest = $this->requestStack->getCurrentRequest();
  }

  /**
   * Registers cache tags for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being tracked.
   */
  public function registerLoadedEntity(EntityInterface $entity) {
    if ($this->shouldTrack($entity)) {
      $tags = $entity->getCacheTags();
      foreach ($tags as $tag) {
        $this->tagsFromLoadedEntities[$tag] = $tag;
      }
    }
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
  public function onResponse(ResponseEvent $responseEvent) {
    // Nothing to do if admins disabled this module.
    $operation_mode = $this->config->get('operation_mode');
    if ($operation_mode === 'disabled') {
      return;
    }

    // Skip checking if this is an admin page and the config is set to only
    // check front-end pages.
    $skip_admin = $this->config->get('skip_admin') ?? TRUE;
    if ($skip_admin) {
      $active_theme = $this->themeManager->getActiveTheme()->getName();
      $admin_theme = $this->configFactory->get('system.theme')->get('admin');
      if ($active_theme === $admin_theme) {
        return;
      }
    }

    // Abort if this response does not contain cache metadata.
    $response = $responseEvent->getResponse();
    if (!($response instanceof CacheableResponseInterface)) {
      return;
    }

    // Do not track pages set to be skipped in config.
    $skip_urls = $this->config->get('skip_urls') ?? [];
    $current_path = $this->currentRequest->getPathInfo();
    if (in_array($current_path, $skip_urls, TRUE)) {
      return;
    }

    // Never fail hard on our own config page to avoid smart users locking
    // themselves out of the house.
    if ($operation_mode === 'strict' &&
      !$skip_admin &&
      $this->routeMatch->getRouteName() === 'cmc.settings') {
      return;
    }

    $diff = array_diff($this->tagsFromLoadedEntities, $response->getCacheableMetadata()->getCacheTags());
    if (!empty($diff)) {
      if ($operation_mode === 'errors') {
        $html = $this->generateHtmlErrorMessage($response, $diff);
        // @todo Troubleshoot this further. It seems not to work well with
        // BigPipe.
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   *   TRUE if this entity should be tracked, FALSE otherwise.
   */
  private function shouldTrack(EntityInterface $entity): bool {
    // Only content entities for now.
    if (!($entity instanceof ContentEntityInterface)) {
      return FALSE;
    }
    // Skip this one as we expect it will be caught by the node cache tag.
    if ($entity instanceof ContentModerationState) {
      return FALSE;
    }
    // Allow modules to modify this.
    $skip = $this->moduleHandler->invokeAll('cmc_skip_tracking', [$entity]);
    // If at least one module wants to skip the tracking, bail out.
    if (in_array(TRUE, $skip, TRUE)) {
      return FALSE;
    }
    return TRUE;
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
    $body = $crawler->filterXPath('//div[@class="layout-container"]');
    if ($body->count() > 0) {
      $warnings = '<p>The following cache tags were not applied to the page.</p><ol><li><pre>' . implode('</pre></li><li><pre>', $diff) . '</pre></li></ol>';
      $tag = '<div';
      if ($body->getNode(0)->hasAttributes()) {
        foreach ($body->getNode(0)->attributes as $attribute) {
          $tag .= sprintf(' %s="%s"', $attribute->nodeName, htmlspecialchars($attribute->nodeValue, ENT_QUOTES));
        }
      }
      // Close the opening tag.
      $tag .= '>';
      $html = str_replace($tag,
        $tag . $warnings,
        $response->getContent());
    }
    return $html;
  }

}
