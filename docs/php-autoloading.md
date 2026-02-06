# PHP Class Autoloading

> **Note:** This document describes the pre-Composer autoloading architecture (before v4.4). Since v4.4, Rondo Club uses Composer PSR-4 autoloading with namespaced classes (e.g., `Rondo\Core\PostTypes`). The conditional initialization pattern described below is still used in `rondo_init()`. Class names shown here reflect the current naming.

## Overview

Rondo Club uses a conditional class loading system to optimize performance. Instead of loading all PHP classes on every page request, classes are loaded only when they are needed.

## How It Works

### Composer PSR-4 Autoloader

Since v4.4, Rondo Club uses Composer's PSR-4 autoloader. Classes are organized under the `Rondo\` namespace and mapped to the `includes/` directory.

Previously, the `rondo_autoloader()` function was registered with PHP's SPL autoload system:

```php
function rondo_autoloader($class_name) {
    // Only handle RONDO_ prefixed classes
    if (strpos($class_name, 'RONDO_') !== 0) {
        return;
    }

    $class_map = [
        'RONDO_Post_Types' => 'class-post-types.php',
        // ... other classes
    ];

    if (isset($class_map[$class_name])) {
        require_once RONDO_THEME_DIR . '/' . $class_map[$class_name];
    }
}
spl_autoload_register('rondo_autoloader');
```

### Conditional Initialization

The `rondo_init()` function determines which classes to instantiate based on the request context:

| Context | Classes Loaded |
|---------|----------------|
| **All Requests** | Post Types, Taxonomies, Access Control |
| **iCal Feed** | iCal Feed (early return for performance) |
| **REST API** | Auto Title, Inverse Relationships, Comment Types, REST API, Import classes |
| **Admin** | Auto Title, Inverse Relationships, Comment Types, Reminders, iCal Feed |
| **Cron** | Auto Title, Inverse Relationships, Comment Types, Reminders |
| **Frontend** | iCal Feed |

## Class Categories

### Core Classes (Always Loaded)

These classes are essential for WordPress integration and must be loaded on every request:

- **Rondo\Core\PostTypes** - Registers custom post types (person, team)
- **Rondo\Core\Taxonomies** - Registers taxonomies (labels, relationship types)
- **Rondo\Core\AccessControl** - Row-level security filtering

### Content Management Classes

These classes handle content creation and modification:

- **Rondo\Core\AutoTitle** - Auto-generates post titles
- **Rondo\Core\InverseRelationships** - Syncs bidirectional relationships
- **Rondo\Collaboration\CommentTypes** - Notes and activities system

Loaded for: Admin, REST API, Cron

### REST API Classes

These classes provide REST API endpoints:

- **Rondo\REST\Api** - Custom `/rondo/v1/` endpoints
- **Rondo\Import\VCardImport** - vCard import
- **Rondo\Import\GoogleContactsImport** - Google Contacts import

Loaded for: REST API requests only

### Utility Classes

- **Rondo\Core\Reminders** - Daily reminder cron job (Admin, Cron only)
- **Rondo\Calendar\ICalFeed** - Calendar feed generation (All requests for hook registration)

## Context Detection

The system uses helper functions to detect the request context:

```php
function rondo_is_rest_request() {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return true;
    }
    // Check URL pattern before REST_REQUEST is defined
    $rest_prefix = rest_get_url_prefix();
    return strpos($_SERVER['REQUEST_URI'], '/' . $rest_prefix . '/') !== false;
}

function rondo_is_ical_request() {
    return strpos($_SERVER['REQUEST_URI'], '/prm-ical/') !== false;
}
```

## Adding New Classes

When adding a new PHP class:

1. **Create a namespaced class** in `includes/`:
   ```php
   namespace Rondo\YourNamespace;

   class YourClass {
       // ...
   }
   ```

2. **Add initialization** in the appropriate context section of `rondo_init()`:
   ```php
   // If only needed for REST API
   if ($is_rest) {
       new Rondo\YourNamespace\YourClass();
   }

   // If always needed
   new Rondo\YourNamespace\YourClass();
   ```

## Performance Benefits

The conditional loading system provides several benefits:

1. **Reduced memory usage** - Only loads classes that are actually used
2. **Faster page loads** - Frontend requests skip admin/import classes
3. **Optimized REST calls** - Import classes only load for API requests
4. **iCal optimization** - Early return for feed requests

## Debugging

To see which classes are loaded on a request, you can temporarily add logging:

```php
function rondo_autoloader($class_name) {
    if (strpos($class_name, 'Rondo\\') !== 0) return;

    error_log('Autoloading: ' . $class_name);
    // ... rest of autoloader
}
```
