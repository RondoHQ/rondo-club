# Phase 96: REST API - Research

**Researched:** 2026-01-21
**Domain:** WordPress REST API for custom post type (stadion_feedback)
**Confidence:** HIGH

## Summary

This phase implements REST API endpoints for the `stadion_feedback` custom post type under `stadion/v1/feedback`. The codebase already has extensive REST API patterns established through `Stadion\REST\Base` and multiple domain-specific REST classes (Todos, Workspaces, People, etc.).

The key architectural decision is to follow the existing patterns rather than extending the built-in `wp/v2` REST controller. The codebase uses custom `stadion/v1` namespace endpoints with explicit formatting, permission callbacks, and ACF field handling.

WordPress Application Passwords (built-in since WP 5.6) provide Basic Auth support out of the box for all REST endpoints. No additional authentication setup is required - the `is_user_logged_in()` function already works with Application Password authentication.

**Primary recommendation:** Create a new `Stadion\REST\Feedback` class following the exact patterns of `Stadion\REST\Todos` - register routes via `rest_api_init`, use `Base` class methods for sanitization and permission checks, format responses explicitly.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | Core WP | Route registration, request/response handling | Native, well-documented |
| ACF Pro | Required | Field storage and retrieval | Already used throughout codebase |
| `Stadion\REST\Base` | Internal | Shared permission checks and response formatting | Established pattern in codebase |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `WP_REST_Server` | Core WP | HTTP method constants (READABLE, CREATABLE, etc.) | Route registration |
| `WP_REST_Response` | Core WP | Response formatting | All endpoint callbacks |
| `WP_Error` | Core WP | Error responses | Validation and error handling |
| `WP_Application_Passwords` | Core WP | API authentication | Built-in, no setup needed |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom `stadion/v1` endpoints | Extend `wp/v2/feedback` controller | `stadion/v1` matches existing patterns, gives more control over response format |
| Manual permission checks | `rest_prepare_*` filters | Manual checks are explicit, match existing code style |

**Installation:**
No new packages required - all dependencies are already in place.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-rest-base.php       # Base class (exists)
├── class-rest-feedback.php   # NEW: Feedback REST endpoints
└── ...
```

### Pattern 1: REST Controller Class Structure
**What:** New REST classes extend `Stadion\REST\Base` and register routes in constructor via `rest_api_init`
**When to use:** All custom REST endpoints under `stadion/v1`
**Example:**
```php
// Source: includes/class-rest-todos.php (existing pattern)
namespace Stadion\REST;

class Feedback extends Base {
    public function __construct() {
        parent::__construct();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            'stadion/v1',
            '/feedback',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_feedback_list' ],
                'permission_callback' => [ $this, 'check_user_logged_in' ],
                'args'                => [
                    'per_page' => [
                        'default'           => 10,
                        'validate_callback' => fn($p) => is_numeric($p) && $p > 0 && $p <= 100,
                    ],
                    // ... more args
                ],
            ]
        );
        // ... more routes
    }
}
```

### Pattern 2: Permission Callbacks
**What:** Use existing Base class methods plus feedback-specific checks
**When to use:** Every endpoint's `permission_callback`
**Example:**
```php
// Source: includes/class-rest-base.php + includes/class-rest-todos.php
// Permission: Any logged-in user (Application Password or cookie auth)
'permission_callback' => function() {
    return is_user_logged_in();
}

// Permission: User can access specific feedback (own or admin)
public function check_feedback_access( $request ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $feedback_id = (int) $request->get_param( 'id' );
    $feedback = get_post( $feedback_id );

    if ( ! $feedback || $feedback->post_type !== 'stadion_feedback' ) {
        return false;
    }

    // Admins can access all
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    // Users can only access their own
    return (int) $feedback->post_author === get_current_user_id();
}
```

### Pattern 3: Response Formatting with Author Embed
**What:** Include author details inline to avoid extra requests (per CONTEXT.md decision)
**When to use:** All feedback list/single responses
**Example:**
```php
// Format feedback with embedded author
private function format_feedback( $post ) {
    $author = get_userdata( $post->post_author );

    return [
        'id'      => $post->ID,
        'title'   => $this->sanitize_text( $post->post_title ),
        'content' => $this->sanitize_rich_content( $post->post_content ),
        'author'  => [
            'id'    => (int) $post->post_author,
            'name'  => $author ? $author->display_name : '',
            'email' => $author ? $author->user_email : '',
        ],
        'date'    => $post->post_date_gmt,
        'meta'    => [
            'feedback_type'      => get_field( 'feedback_type', $post->ID ),
            'status'             => get_field( 'status', $post->ID ),
            'priority'           => get_field( 'priority', $post->ID ),
            'browser_info'       => get_field( 'browser_info', $post->ID ),
            'app_version'        => get_field( 'app_version', $post->ID ),
            'url_context'        => get_field( 'url_context', $post->ID ),
            'steps_to_reproduce' => get_field( 'steps_to_reproduce', $post->ID ),
            'expected_behavior'  => get_field( 'expected_behavior', $post->ID ),
            'actual_behavior'    => get_field( 'actual_behavior', $post->ID ),
            'use_case'           => get_field( 'use_case', $post->ID ),
            'attachments'        => $this->format_attachments( get_field( 'attachments', $post->ID ) ),
        ],
    ];
}
```

### Pattern 4: Pagination Headers
**What:** Use `X-WP-Total` and `X-WP-TotalPages` headers for pagination info
**When to use:** List endpoints
**Example:**
```php
// Source: WordPress REST API conventions
public function get_feedback_list( $request ) {
    $per_page = (int) $request->get_param( 'per_page' );
    $page     = (int) $request->get_param( 'page' );

    // Query feedback with pagination
    $query = new \WP_Query( [
        'post_type'      => 'stadion_feedback',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        // ... filters
    ] );

    $formatted = array_map( [ $this, 'format_feedback' ], $query->posts );

    $response = rest_ensure_response( $formatted );
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}
```

### Anti-Patterns to Avoid
- **Extending WP_REST_Controller directly:** The codebase uses custom Base class pattern instead
- **Using filters on wp/v2 endpoints:** Create explicit stadion/v1 endpoints with full control
- **Separate author lookup calls:** Embed author info in response per CONTEXT.md decision
- **Using custom database tables:** Feedback uses CPT + ACF, not custom tables

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Basic Auth parsing | Custom header parsing | WordPress does this automatically | `is_user_logged_in()` works with App Passwords |
| Response formatting | Manual JSON encoding | `rest_ensure_response()` | Handles content types, encoding |
| Error responses | Custom error format | `WP_Error` with status code | Standard WordPress REST format |
| Input sanitization | Custom sanitizers | `sanitize_text_field()`, `wp_kses_post()` | WordPress core functions |
| Permission checks | Custom auth middleware | `permission_callback` parameter | Native REST API feature |
| ACF field access | Direct meta queries | `get_field()`, `update_field()` | ACF handles serialization |

**Key insight:** WordPress Application Passwords work automatically with the REST API. Any authenticated endpoint (using `is_user_logged_in()` or similar in permission_callback) automatically supports Application Password authentication via Basic Auth header. No additional code needed.

## Common Pitfalls

### Pitfall 1: Assuming Cookie Auth is Required
**What goes wrong:** Thinking Application Passwords need special handling
**Why it happens:** Confusion about WP auth mechanisms
**How to avoid:** WordPress automatically authenticates Basic Auth requests with Application Passwords. The `is_user_logged_in()` function returns true for both cookie auth AND Application Password auth.
**Warning signs:** Code that explicitly checks for Basic Auth header or parses credentials manually

### Pitfall 2: Exposing Admin-Only Fields to Regular Users
**What goes wrong:** Users can modify status/priority through the API
**Why it happens:** Not checking user role before allowing field updates
**How to avoid:** In update callback, check if user is admin before allowing status/priority changes
**Warning signs:** Single permission callback for entire endpoint without field-level checks

### Pitfall 3: Not Filtering List Results by Ownership
**What goes wrong:** Users see other users' feedback in list endpoint
**Why it happens:** Forgetting that feedback is user-scoped (not workspace-scoped per Phase 95)
**How to avoid:** Apply `author` filter in WP_Query for non-admin users
**Warning signs:** List endpoint returns all feedback regardless of user

### Pitfall 4: Forgetting Pagination Headers
**What goes wrong:** Clients can't implement pagination correctly
**Why it happens:** Using `rest_ensure_response()` without adding headers
**How to avoid:** Always add `X-WP-Total` and `X-WP-TotalPages` headers to list responses
**Warning signs:** Missing pagination info in API responses

### Pitfall 5: Not Validating ACF Select Field Values
**What goes wrong:** Invalid values saved to status/type/priority fields
**Why it happens:** Trusting client input
**How to avoid:** Validate against allowed values from ACF field definition
**Warning signs:** Database contains values not in ACF choices array

## Code Examples

Verified patterns from official sources and existing codebase:

### Route Registration with Full CRUD
```php
// Source: includes/class-rest-todos.php pattern
public function register_routes() {
    // GET /feedback - List
    register_rest_route( 'stadion/v1', '/feedback', [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_feedback_list' ],
        'permission_callback' => fn() => is_user_logged_in(),
        'args'                => $this->get_list_args(),
    ] );

    // POST /feedback - Create
    register_rest_route( 'stadion/v1', '/feedback', [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'create_feedback' ],
        'permission_callback' => fn() => is_user_logged_in(),
    ] );

    // GET /feedback/{id} - Single
    register_rest_route( 'stadion/v1', '/feedback/(?P<id>\d+)', [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_feedback' ],
        'permission_callback' => [ $this, 'check_feedback_access' ],
        'args'                => [
            'id' => [ 'validate_callback' => fn($p) => is_numeric($p) ],
        ],
    ] );

    // PATCH /feedback/{id} - Update
    register_rest_route( 'stadion/v1', '/feedback/(?P<id>\d+)', [
        'methods'             => \WP_REST_Server::EDITABLE,
        'callback'            => [ $this, 'update_feedback' ],
        'permission_callback' => [ $this, 'check_feedback_access' ],
        'args'                => [
            'id' => [ 'validate_callback' => fn($p) => is_numeric($p) ],
        ],
    ] );

    // DELETE /feedback/{id} - Delete
    register_rest_route( 'stadion/v1', '/feedback/(?P<id>\d+)', [
        'methods'             => \WP_REST_Server::DELETABLE,
        'callback'            => [ $this, 'delete_feedback' ],
        'permission_callback' => [ $this, 'check_feedback_access' ],
        'args'                => [
            'id' => [ 'validate_callback' => fn($p) => is_numeric($p) ],
        ],
    ] );
}
```

### Error Response Format (WordPress Standard)
```php
// Source: WordPress REST API conventions, existing codebase
return new \WP_Error(
    'rest_forbidden',          // code
    __( 'You do not have permission to access this feedback.', 'stadion' ), // message
    [ 'status' => 403 ]        // data with HTTP status
);

// For validation errors with params
return new \WP_Error(
    'rest_invalid_param',
    __( 'Invalid feedback type.', 'stadion' ),
    [
        'status' => 400,
        'params' => [ 'feedback_type' => 'Must be "bug" or "feature_request"' ],
    ]
);
```

### Field-Level Permission Check for Updates
```php
// Admin-only fields check in update callback
public function update_feedback( $request ) {
    $feedback_id = (int) $request->get_param( 'id' );
    $feedback = get_post( $feedback_id );
    $is_admin = current_user_can( 'manage_options' );

    // Status can only be changed by admins
    $status = $request->get_param( 'status' );
    if ( $status !== null && ! $is_admin ) {
        return new \WP_Error(
            'rest_forbidden',
            __( 'Only administrators can change feedback status.', 'stadion' ),
            [ 'status' => 403 ]
        );
    }

    // Priority can only be changed by admins
    $priority = $request->get_param( 'priority' );
    if ( $priority !== null && ! $is_admin ) {
        return new \WP_Error(
            'rest_forbidden',
            __( 'Only administrators can change feedback priority.', 'stadion' ),
            [ 'status' => 403 ]
        );
    }

    // Regular users can update title, content, type-specific fields
    // ... continue with updates
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Basic Auth plugin | Application Passwords | WordPress 5.6 (Dec 2020) | No plugin needed |
| Cookie-only REST auth | Cookie + App Password | WordPress 5.6 | API accessible from external tools |
| Custom ACF REST exposure | ACF built-in REST support | ACF 5.11 | `show_in_rest: 1` in field group |

**Deprecated/outdated:**
- Basic Authentication plugin: Replaced by Application Passwords
- Manual nonce handling for external API: Not needed with App Passwords

## Open Questions

Things that couldn't be fully resolved:

1. **Attachments handling in create/update**
   - What we know: ACF gallery field stores attachment IDs
   - What's unclear: Should API accept media IDs directly, or handle file uploads?
   - Recommendation: Accept media IDs for API (file upload is separate wp/v2/media endpoint)

2. **Rate limiting mentioned in context as "no rate limiting"**
   - What we know: Context explicitly says no rate limiting for this phase
   - What's unclear: Future needs for rate limiting
   - Recommendation: Follow context, skip rate limiting. Can add later if needed.

## Sources

### Primary (HIGH confidence)
- `includes/class-rest-base.php` - Base class patterns
- `includes/class-rest-todos.php` - CRUD endpoint patterns
- `includes/class-rest-workspaces.php` - Complex permission patterns
- `includes/class-rest-api.php` - Main API class patterns
- `includes/class-post-types.php` - stadion_feedback CPT registration
- `acf-json/group_feedback_fields.json` - ACF field definitions
- `.planning/phases/96-rest-api/96-CONTEXT.md` - User decisions

### Secondary (MEDIUM confidence)
- [WordPress REST API Authentication Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) - Application Passwords docs
- [Application Passwords Integration Guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) - Implementation details
- `includes/carddav/class-auth-backend.php` - Example of Application Password validation

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Extensive existing patterns in codebase
- Architecture: HIGH - Multiple similar REST classes to follow
- Pitfalls: HIGH - Based on codebase analysis and WordPress REST conventions
- Application Password auth: HIGH - Verified in official docs and existing carddav implementation

**Research date:** 2026-01-21
**Valid until:** 90 days (stable WordPress REST patterns)
