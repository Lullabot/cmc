<?php

declare(strict_types=1);

namespace Drupal\cmc\EventSubscriber;

use Drupal\cmc\EntityCacheTagCollector;
use Drupal\cmc\LeakyCache\DisplayLeakyCache;
use Drupal\cmc\LeakyCache\LeakyCacheInterface;
use Drupal\cmc\LeakyCache\NullLeakyCache;
use Drupal\cmc\LeakyCache\StrictLeakyCache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The module's configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The leak processor.
   *
   * @var \Drupal\cmc\LeakyCache\LeakyCacheInterface
   */
  private readonly LeakyCacheInterface $leakProcessor;

  /**
   * Class constructor.
   *
   * @param \Drupal\cmc\EntityCacheTagCollector $entityCacheTagCollector
   *   The entity cache tag collector.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
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
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly RequestStack $requestStack,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    $this->config = $this->configFactory->get('cmc.settings');
    $this->leakProcessor = $this->factoryLeakProcessor(
      (string) $this->config->get('operation_mode')
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
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $response_event
   *   The response event.
   *
   * @return void
   */
  public function onResponse(ResponseEvent $response_event): void {
    // Avoid Ajax and sub-requests.
    if (
      $response_event->getRequest()->isXmlHttpRequest()
      || !$response_event->isMainRequest()
    ) {
      return;
    }

    $tags_from_entities = $this->entityCacheTagCollector->getTagsFromLoadedEntities();
    // Nothing to do if admins disabled this module or there are no tags.
    if (empty($tags_from_entities) || $this->leakProcessor instanceof NullLeakyCache) {
      return;
    }

    if ($this->shouldSkipAdminCheck()) {
      return;
    }

    // Abort if this response does not contain cache metadata.
    $response = $response_event->getResponse();
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
    // If the response contains the cache tag for the entity list, then none of
    // the individual entities for that type should be reported.
    $tags_from_response = $response->getCacheableMetadata()->getCacheTags();
    $tags_from_entities = $this->removeEntitiesWithListTag($tags_from_entities, $tags_from_response);

    $diff = array_diff($tags_from_entities, $tags_from_response);
    if (!empty($diff)) {
      $this->leakProcessor->processLeaks($diff, $response);
    }
  }

  /**
   * Removes entity tags that are covered by list cache tags from the response.
   *
   * This method filters out entity-specific cache tags from a given list based
   * on the list cache tags present in the response. Entity-specific tags are
   * those that match an entity type ID followed by a colon. If the list cache
   * tags exist in the response, any matching entity tags are removed.
   *
   * @param string[] $tags_from_entities
   *   An array of cache tags corresponding to specific entities.
   * @param string[] $tags_from_response
   *   An array of cache tags derived from the response, including list cache
   *   tags.
   *
   * @return string[]
   *   An array of cache tags with entity-specific tags removed if they are
   *   covered by the list cache tags from the response.
   */
  private function removeEntitiesWithListTag(array $tags_from_entities, array $tags_from_response): array {
    $entity_types = $this->entityTypeManager->getDefinitions();
    // Ensure we are dealing with content entity types.
    $content_entity_types = array_filter(
      $entity_types,
      static fn (EntityTypeInterface $entity_type) => $entity_type instanceof ContentEntityTypeInterface,
    );
    $all_entity_list_tag_pairs = array_reduce(
      $content_entity_types,
      fn (array $carry, EntityTypeInterface $entity_type) => [
        ...$carry,
        // Gather the entity type ID, as well as the list tag. This is so we can
        // detect the entities of a certain type later.
        ...array_map(
          fn (string $list_tag) => [$entity_type->id(), $list_tag],
          $entity_type->getListCacheTags(),
        ),
      ],
      [],
    );
    // Now detect which ones are in the response. We cannot intersect the arrays
    // because we want to detect the bundle-specific cache tags. Like:
    // node_list:article.
    // @see https://www.drupal.org/node/3107058
    $list_tags_from_response = array_filter(
      $all_entity_list_tag_pairs,
      static fn (array $list_tag_pair) => array_reduce(
        $tags_from_response,
        static fn (bool $found, string $cache_tag) => $found || str_starts_with($cache_tag, $list_tag_pair[1]),
        FALSE
      ),
    );
    // Remove individual entity tags that are covered by the list tags.
    $entity_types_to_remove_tags = array_unique(
      array_map(static fn (array $list_tag_pair) => $list_tag_pair[0], $list_tags_from_response),
    );
    return array_filter(
      $tags_from_entities,
      // Only the tags that don't start with one of the entity types from the
      // list are returned.
      static fn (string $cache_tag) => !array_reduce(
        $entity_types_to_remove_tags,
        // For each $cache_tag check if they start with '{entity_type_id}:'.
        static fn (bool $found, string $entity_type_id) => $found || str_starts_with($cache_tag, $entity_type_id . ':'),
        FALSE
      )
    );
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
