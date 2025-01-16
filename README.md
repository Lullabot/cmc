# Cacheability Metadata Checker (CMC)

A Drupal module that helps developers identify missing cache tags in their pages by tracking loaded entities and verifying their cache tags are properly bubbled up to the response.

## Overview

The Cacheability Metadata Checker monitors entity loads during page requests and verifies that all cache tags from those entities are present in the final response. This helps ensure proper cache invalidation by catching situations where entity cache tags are accidentally stripped or forgotten.

## Features

- Tracks loaded content entities and their cache tags
- Configurable operation modes (disabled, display errors, strict)
- Option to check only front-end pages or include admin pages
- Ability to skip specific URLs
- API for other modules to exclude certain entities from tracking

## Configuration

Visit `/admin/config/development/cmc` to configure the module. The following options are available:

### Operation Mode

- **Disabled**: No cache tag checking (default)
- **Display errors**: Shows missing cache tags at the top of affected pages
- **Strict**: Throws exceptions when cache tags are missing (recommended for development)

### Front-end Only

When enabled (default), the module only checks pages using the default theme. When disabled, it also checks admin pages using the admin theme.

### Skip URLs

Define specific paths that should be excluded from cache tag checking.

## API

### Hook: hook_cmc_skip_tracking()

Modules can implement this hook to exclude certain entities from cache tag tracking:

```php
/**
 * Implements hook_cmc_skip_tracking().
 */
function mymodule_cmc_skip_tracking(EntityInterface $entity): bool {
  // Skip tracking for specific entity types or conditions.
  if ($entity->getEntityTypeId() === 'my_entity_type') {
    return TRUE;
  }
  return FALSE;
}
```

## Best Practices

1. Enable "Strict" mode during development to catch cache tag issues early
2. Use "Display errors" mode on staging environments for visual feedback
3. Disable the module in production environments
4. Consider excluding admin pages if you're only concerned with front-end caching
