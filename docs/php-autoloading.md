# PHP Class Autoloading

## Overview

Caelis uses a conditional class loading system to optimize performance. Instead of loading all PHP classes on every page request, classes are loaded only when they are needed.

## How It Works

### SPL Autoloader

The `prm_autoloader()` function is registered with PHP's SPL autoload system. When a class is referenced for the first time, PHP automatically calls this function to load the class file.

```php
function prm_autoloader($class_name) {
    // Only handle PRM_ prefixed classes
    if (strpos($class_name, 'PRM_') !== 0) {
        return;
    }
    
    $class_map = [
        'PRM_Post_Types' => 'class-post-types.php',
        // ... other classes
    ];
    
    if (isset($class_map[$class_name])) {
        require_once PRM_PLUGIN_DIR . '/' . $class_map[$class_name];
    }
}
spl_autoload_register('prm_autoloader');
```

### Conditional Initialization

The `prm_init()` function determines which classes to instantiate based on the request context:

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

- **PRM_Post_Types** - Registers custom post types (person, company, important_date)
- **PRM_Taxonomies** - Registers taxonomies (labels, relationship types, date types)
- **PRM_Access_Control** - Row-level security filtering

### Content Management Classes

These classes handle content creation and modification:

- **PRM_Auto_Title** - Auto-generates post titles
- **PRM_Inverse_Relationships** - Syncs bidirectional relationships
- **PRM_Comment_Types** - Notes and activities system

Loaded for: Admin, REST API, Cron

### REST API Classes

These classes provide REST API endpoints:

- **PRM_REST_API** - Custom `/prm/v1/` endpoints
- **PRM_Monica_Import** - Monica CRM import
- **PRM_VCard_Import** - vCard import
- **PRM_Google_Contacts_Import** - Google Contacts import

Loaded for: REST API requests only

### Utility Classes

- **PRM_Reminders** - Daily reminder cron job (Admin, Cron only)
- **PRM_ICal_Feed** - Calendar feed generation (All requests for hook registration)

## Context Detection

The system uses helper functions to detect the request context:

```php
function prm_is_rest_request() {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return true;
    }
    // Check URL pattern before REST_REQUEST is defined
    $rest_prefix = rest_get_url_prefix();
    return strpos($_SERVER['REQUEST_URI'], '/' . $rest_prefix . '/') !== false;
}

function prm_is_ical_request() {
    return strpos($_SERVER['REQUEST_URI'], '/prm-ical/') !== false;
}
```

## Adding New Classes

When adding a new PHP class:

1. **Add to autoloader map** in `functions.php`:
   ```php
   $class_map = [
       // existing entries...
       'PRM_Your_Class' => 'class-your-class.php',
   ];
   ```

2. **Add initialization** in the appropriate context section of `prm_init()`:
   ```php
   // If only needed for REST API
   if ($is_rest) {
       new PRM_Your_Class();
   }
   
   // If always needed
   new PRM_Your_Class();
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
function prm_autoloader($class_name) {
    if (strpos($class_name, 'PRM_') !== 0) return;
    
    error_log('Autoloading: ' . $class_name);
    // ... rest of autoloader
}
```

