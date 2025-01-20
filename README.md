# Cacheability Metadata Checker (CMC)

A Drupal module that helps developers identify missing cache tags in their pages by tracking loaded entities and
verifying their cache tags are properly bubbled up to the response.

## Features

- Tracks loaded entities and their cache tags, compares with tags bubbled to the HTTP response object
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

When enabled (default), the module only checks pages using the default theme. When disabled, it also checks admin
pages using the admin theme.

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

1. Enable "Strict" mode during development to catch cache tag issues early.
2. Use "Display errors" mode on staging environments for visual feedback or to use tools such as VisualDiff tests.
3. Never enable the module in production environments.
4. Consider excluding admin pages to avoid unrelated issues when developing the front-end theme.

## Coding Standards

The module includes a custom PHPCS sniff that helps maintain Drupal best practices in Twig templates:

### Direct Field Access Sniff

This sniff detects direct field access in Twig templates using patterns like `node.field_*`, `content.field_*`, etc.
This pattern is discouraged because it may introduce caching bugs by leaving out the proper cache tags in the top-level
render array.

#### Installation

1. Install development dependencies:
   ```bash
   composer require --dev drupal/coder squizlabs/php_codesniffer
   ```

2. Register the custom standard:
   ```bash
   vendor/bin/phpcs --config-set installed_paths /path/to/web/modules/custom/cmc/phpcs
   ```
   Note: the example above will set your `installed_paths` to include only the path to this module's sniffers, which
   is likely not what you want. You can pass a comma-separated list of paths to the above command, or include the line
   below in your `phpcs.xml.dist` configuration file:

   ```xml
   <config name="installed_paths" value="../../drupal/coder/coder_sniffer,../../sirbrillig/phpcs-variable-analysis,../../slevomat/coding-standard,../../../web/modules/custom/cmc/phpcs" />
   ```

3. Add the standard to your `phpcs.xml.dist`:
   ```xml
   <rule ref="web/modules/custom/cmc/phpcs/ruleset.xml"/>
   ```

   Also make sure you allow `twig` as one of the extensions to be sniffed, in case you have an `<arg name="extensions">`
   config value.

4. Run the sniffer:
   ```bash
   vendor/bin/phpcs /path/to/your/templates `--standard=CMC`
   ```

   If you included the standard in your `phpcs.xml.dist` configuration file, you can omit the `--standard=CMC` flag.


#### Skip Conditions

The sniff will automatically skip the warning under these conditions:

1. If the template renders the full `content` variable:
   ```twig
   {{ content }}
   ```

2. If the template renders cache metadata explicitly:
   ```twig
   {{ content['#cache'] }}
   ```

3. If you explicitly opt-out using the special comment:
   ```twig
   {# cmc_direct_field_access_sniff_opt_out #}
   ```

#### Example Usage

❌ Not recommended:
```twig
{# Direct field access will trigger warning #}
{{ node.field_image }}
{{ content.field_tags }}
{{ media.field_media_image }}
```

✅ Recommended:
```twig
{# Render the full content variable #}
{{ content }}

{# Render cache metadata & assets explicitly #}
{{ node.field_image }}
{{ {'#cache': content['#cache'], '#attached': content['#attached']} }}

{# Or opt-out if you know this is being handled elsewhere #}
{# cmc_direct_field_access_sniff_opt_out #}
{{ node.field_special_case }}
```

Example warning:
```
FILE: /path/to/template.html.twig
--------------------------------------------------------------------------------
FOUND 1 WARNING AFFECTING 1 LINE
--------------------------------------------------------------------------------
 15 | WARNING | Direct field access using "node.field_image" is not recommended.
    |         | Try to always render full render arrays that come from the backend.
--------------------------------------------------------------------------------
```

