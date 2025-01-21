<?php

declare(strict_types=1);

namespace Drupal\cmc\EventSubscriber;

use Drupal\cmc\EntityCacheTagCollector;
use Drupal\cmc\LeakyCache\DisplayLeakyCache;
use Drupal\cmc\LeakyCache\LeakyCacheInterface;
use Drupal\cmc\LeakyCache\NullLeakyCache;
use Drupal\cmc\LeakyCache\StrictLeakyCache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tracks cache tags from entities used for the current request.
 */
class LoadedEntitySubscriber implements EventSubscriberInterface {

  /**
   * The leak processor.
   *
   * @var \Drupal\cmc\LeakyCache\LeakyCacheInterface
   */
  private readonly LeakyCacheInterface $leakProcessor;

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
    $this->leakProcessor = $this->factoryLeakProcessor(
      (string) $this->configFactory->get('cmc.settings')->get('operation_mode')
    );
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
   */
  public function onResponse(ResponseEvent $responseEvent): void {
    $tags_from_entities = $this->entityCacheTagCollector->getTagsFromLoadedEntities();
    // Nothing to do if admins disabled this module or there are no tags.
    if (empty($tags_from_entities) || $this->leakProcessor instanceof NullLeakyCache) {
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
    if ($this->routeMatch->getRouteName() === 'cmc.settings') {
      return;
    }

    $diff = array_diff(
      $tags_from_entities,
      $response->getCacheableMetadata()->getCacheTags(),
    );
    if (!empty($diff)) {
      $this->leakProcessor->processLeaks($diff, $response);
    }
  }

  /**
   * Determines if the admin page check should be skipped.
   *
   * @return bool
   *   TRUE if the check should be skipped, FALSE otherwise.
   */
  private function shouldSkipAdminCheck(): bool {
    $skip_admin = $this->config->get('skip_admin') ?? TRUE;
    if (!$skip_admin) {
      return FALSE;
    }
    $route = $this->routeMatch->getRouteObject();
    if ($route?->getOption('_admin_route')) {
      return TRUE;
    }

    $current_theme = $this->themeManager->getActiveTheme()->getName();
    $admin_theme = $this->configFactory->get('system.theme')->get('admin');

    return $current_theme === $admin_theme;
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
   * Instantiates the leak processor.
   *
   * @param string $operation_mode
   *   The operation mode.
   *
   * @return \Drupal\cmc\LeakyCache\LeakyCacheInterface
   *   The processor.
   */
  private function factoryLeakProcessor(string $operation_mode): LeakyCacheInterface {
    return match ($operation_mode) {
      'strict' => new StrictLeakyCache(),
      'errors' => new DisplayLeakyCache(),
      default => new NullLeakyCache(),
    };
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
    ];
  }

}
