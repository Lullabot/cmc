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
- Custom PHPCS sniff for Twig template best practices

## Configuration

Visit `/admin/config/development/cmc` to configure the module. The following options are available:

### Operation Mode

- **Disabled**: No cache tag checking (default)
- **Display errors**: Shows missing cache tags at the top of affected pages
- **Strict**: Throws exceptions when cache tags are missing (recommended for development)

### Skip Admin Pages

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

## Coding Standards

The module includes a custom PHPCS sniff that helps maintain Drupal best practices in Twig templates:

### Direct Field Access Sniff

This sniff detects direct field access in Twig templates using the pattern `node.field_foo`. This pattern is discouraged because:
- It bypasses field formatters and rendering systems
- It makes templates less maintainable
- It can lead to security issues if proper escaping isn't used

To use the sniff:

1. Install development dependencies:
   ```bash
   composer require --dev drupal/coder squizlabs/php_codesniffer
   ```

2. Register the custom standard:
   ```bash
   vendor/bin/phpcs --config-set installed_paths /path/to/web/modules/custom/cmc/phpcs
   ```
Note: the example above will set your `installed_paths` to include only the
path to this module's sniffers, which is likely not what you want. You can pass
a comma-separated list of paths to the above command, or include the line below
in your `phpcs.xml.dist` configuration file, if you are using one:

```
<config name="installed_paths" value="../../drupal/coder/coder_sniffer,../../sirbrillig/phpcs-variable-analysis,../../slevomat/coding-standard,../../../web/modules/custom/cmc/phpcs" />
```

If you are using a `phpcs.xml.dist` configuration file in your project, you may
also want to add the standard directly there:

```
<rule ref="web/modules/custom/cmc/phpcs/ruleset.xml"/>
```

Also make sure you allow `twig` as one of the extensions to be sniffed, in case
you have an `<arg name="extensions">` config value.

3. Run the sniffer:
   ```bash
   vendor/bin/phpcs --standard=CMC /path/to/your/templates
   ```

If you included the standard in your `phpcs.xml.dist` configuration file, you
can omit the `--standard=CMC` flag mentioned above.

Example warning:
```
FILE: /path/to/template.html.twig
--------------------------------------------------------------------------------
FOUND 1 WARNING AFFECTING 1 LINE
--------------------------------------------------------------------------------
 15 | WARNING | Direct field access using "node.field_image" is not recommended.
    |         | Try to always render full variables that come from the backend.
--------------------------------------------------------------------------------
```

Instead of direct field access, use:
```twig
{# Recommended #}
{{ content.field_image }}

{# Not recommended #}
{{ node.field_image.value }}
