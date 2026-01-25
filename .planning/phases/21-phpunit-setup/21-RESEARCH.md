# Phase 21: PHPUnit Setup - Research

**Researched:** 2026-01-13
**Domain:** WordPress PHPUnit testing with wp-browser (Codeception)
**Confidence:** HIGH

<research_summary>
## Summary

Researched the wp-browser ecosystem for setting up PHPUnit testing in a WordPress theme project. The standard approach uses wp-browser 4.x (lucatume/wp-browser) which provides Codeception modules specifically designed for WordPress testing.

Key finding: wp-browser version 4 is the current standard (4.5.10 as of Nov 2025), requiring PHP 8.0+ and Codeception 5.x. For themes, the recommended approach is to use the WPLoader module in integration test mode (`loadOnly: false`) which provides database transaction rollback between tests, automatic WordPress environment cleanup, and factory methods for creating test fixtures.

Critical consideration: Stadion is a theme (not a plugin), has ACF Pro as a required dependency, and needs to test custom REST API endpoints, access control, and CPT relationships. The wp-browser setup should point to the existing WordPress installation rather than downloading a fresh one.

**Primary recommendation:** Use wp-browser 4.x with WPLoader module configured for the existing WordPress installation. Create a dedicated `stadion_test` database. Configure ACF Pro activation in tests. Focus on wpunit test type for integration testing of REST API and access control.
</research_summary>

<standard_stack>
## Standard Stack

The established libraries/tools for WordPress integration testing:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| lucatume/wp-browser | ^4.5 | WordPress Codeception modules | 10 years proven, 3.5M+ installs, maintained |
| codeception/codeception | ^5.0 | Testing framework | Industry standard, wp-browser builds on it |
| phpunit/phpunit | ^10.0 or ^11.0 | Underlying test runner | WordPress core test suite uses it |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| codeception/module-asserts | ^3.0 | Assertion helpers | Always (peer dependency) |
| codeception/module-cli | ^2.0 | CLI testing | If testing WP-CLI commands |
| codeception/module-db | ^3.0 | Database helpers | For WPDb module |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| wp-browser | WP-CLI scaffold | WP-CLI is simpler but less flexible; wp-browser has better isolation |
| Codeception | Plain PHPUnit | PHPUnit alone needs manual WordPress bootstrap; wp-browser handles it |
| MySQL test DB | SQLite | SQLite is faster for quick tests but may miss MySQL-specific issues |

**Installation:**
```bash
composer require --dev lucatume/wp-browser "^4.5"
```

wp-browser pulls in all required Codeception dependencies automatically.
</standard_stack>

<architecture_patterns>
## Architecture Patterns

### Recommended Project Structure
```
stadion/
├── tests/
│   ├── .env                    # Test environment config (gitignored)
│   ├── .env.testing           # Template for .env (committed)
│   ├── Support/
│   │   ├── Helper/
│   │   │   └── Wpunit.php     # Custom helper methods
│   │   └── StadionTestCase.php # Base test case with ACF setup
│   └── Wpunit/
│       ├── AccessControl/
│       │   ├── UserIsolationTest.php
│       │   ├── VisibilityRulesTest.php
│       │   └── WorkspacePermissionsTest.php
│       ├── RestApi/
│       │   ├── PeopleEndpointTest.php
│       │   ├── DashboardEndpointTest.php
│       │   └── SearchEndpointTest.php
│       └── DataModel/
│           ├── PersonCptTest.php
│           ├── RelationshipsTest.php
│           └── AcfFieldsTest.php
├── codeception.yml             # Main Codeception config
└── phpunit.xml.dist            # PHPUnit config (optional, for IDE)
```

### Pattern 1: WPLoader with Existing Installation
**What:** Configure WPLoader to use the development WordPress installation
**When to use:** Theme development where WordPress is already installed
**Example:**
```yaml
# codeception.yml
suites:
  Wpunit:
    actor: WpunitTester
    modules:
      enabled:
        - WPLoader
      config:
        WPLoader:
          wpRootFolder: "/path/to/wordpress"  # Not theme path!
          dbUrl: "mysql://root:password@localhost/stadion_test"
          tablePrefix: "wp_"
          domain: "stadion.test"
          adminEmail: "admin@stadion.test"
          plugins:
            - advanced-custom-fields-pro/acf.php
          theme: stadion
          loadOnly: false
```

### Pattern 2: Base Test Case with ACF Setup
**What:** Create a base test case that ensures ACF is loaded and fields are available
**When to use:** Any test that interacts with ACF custom fields
**Example:**
```php
<?php
namespace Tests\Support;

use lucatume\WPBrowser\TestCase\WPTestCase;

abstract class StadionTestCase extends WPTestCase {

    protected function setUp(): void {
        parent::setUp();

        // Ensure ACF is fully loaded
        if (function_exists('acf')) {
            // Load local JSON field groups
            acf_get_local_json_files();
        }
    }

    /**
     * Create a person post with ACF fields
     */
    protected function createPerson(array $args = [], array $acf_fields = []): int {
        $defaults = [
            'post_type' => 'person',
            'post_status' => 'publish',
        ];

        $post_id = self::factory()->post->create(array_merge($defaults, $args));

        foreach ($acf_fields as $field_name => $value) {
            update_field($field_name, $value, $post_id);
        }

        return $post_id;
    }
}
```

### Pattern 3: REST API Test Pattern
**What:** Use WordPress REST infrastructure within integration tests
**When to use:** Testing custom REST endpoints
**Example:**
```php
<?php
namespace Tests\Wpunit\RestApi;

use Tests\Support\StadionTestCase;
use WP_REST_Request;
use WP_REST_Server;

class PeopleEndpointTest extends StadionTestCase {

    private WP_REST_Server $server;

    public function setUp(): void {
        parent::setUp();

        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');
    }

    public function tearDown(): void {
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tearDown();
    }

    public function test_user_can_only_see_own_people(): void {
        // Create two users
        $user1 = self::factory()->user->create(['role' => 'stadion_user']);
        $user2 = self::factory()->user->create(['role' => 'stadion_user']);

        // Create people owned by user1
        wp_set_current_user($user1);
        $person_id = $this->createPerson(['post_title' => 'User1 Contact']);

        // Switch to user2 and request people list
        wp_set_current_user($user2);
        $request = new WP_REST_Request('GET', '/wp/v2/people');
        $response = $this->server->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // User2 should not see User1's contact
        $this->assertEmpty($data);
    }
}
```

### Anti-Patterns to Avoid
- **Using production database:** NEVER configure tests to use production/dev database; always use dedicated test database
- **Not calling parent::setUp():** Always call `parent::setUp()` in snake_case WordPress style
- **Creating fixtures in class setup without cleanup:** Use `wpSetUpBeforeClass()` for shared fixtures and clean in `wpTearDownAfterClass()`
- **Testing against live WordPress:** Use WPLoader's controlled environment, not actual site
</architecture_patterns>

<dont_hand_roll>
## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WordPress bootstrap | Manual require wp-load.php | WPLoader module | Handles transactions, cleanup, state isolation |
| Test database reset | DROP/CREATE scripts | WPLoader transactions | Automatic rollback, faster, no DDL issues |
| User creation | Manual wp_insert_user() | `self::factory()->user->create()` | Handles cleanup, provides defaults |
| Post creation | Manual wp_insert_post() | `self::factory()->post->create()` | Transaction-safe, automatic cleanup |
| REST API simulation | cURL/Guzzle | WP_REST_Server + WP_REST_Request | Tests actual WordPress routing |
| ACF field setup | Raw update_post_meta() | update_field() with ACF loaded | Respects ACF field types and formatting |
| Test configuration | Hardcoded constants | tests/.env file | Environment-specific, gitignored secrets |

**Key insight:** wp-browser exists because WordPress testing has many edge cases (global state, database transactions, hook cleanup). The WPLoader module handles all of this. Trying to bootstrap WordPress manually for tests will lead to flaky tests and state leakage between test runs.
</dont_hand_roll>

<common_pitfalls>
## Common Pitfalls

### Pitfall 1: Database Gets Wiped
**What goes wrong:** Running tests destroys development database
**Why it happens:** WPLoader drops and recreates the database on first run
**How to avoid:** ALWAYS use a dedicated test database (e.g., `stadion_test`), never point to dev database
**Warning signs:** Running `vendor/bin/codecept run` and seeing "Database tables created..."

### Pitfall 2: ACF Fields Not Available in Tests
**What goes wrong:** `get_field()` returns null, ACF functions not found
**Why it happens:** ACF Pro not activated in test environment, or local JSON not loaded
**How to avoid:**
1. Add ACF Pro to `plugins` array in WPLoader config
2. Ensure `acf-json` path is accessible from wpRootFolder
3. Call `acf_get_local_json_files()` in setUp if needed
**Warning signs:** Tests fail with "Call to undefined function get_field()"

### Pitfall 3: Tests Interfere With Each Other
**What goes wrong:** Tests pass individually but fail when run together
**Why it happens:** Global state leakage (current user, query vars, registered hooks)
**How to avoid:**
1. Always call `parent::setUp()` and `parent::tearDown()`
2. Use `wp_set_current_user(0)` before tests that depend on not being logged in
3. Don't rely on static class properties between tests
**Warning signs:** Different results when running full suite vs single test

### Pitfall 4: $wpdb is null in Teardown
**What goes wrong:** "Teardown error — $wpdb is null" during test cleanup
**Why it happens:** Known issue with wp-browser 4.x and SQLite in some configurations
**How to avoid:** Use MySQL for test database, not SQLite; ensure proper WordPress bootstrap
**Warning signs:** Warning about "SQLITE_MAIN_FILE already defined"

### Pitfall 5: Custom Post Types Not Registered
**What goes wrong:** `WP_Query` for 'person' returns empty, REST endpoints 404
**Why it happens:** Theme's `init` hooks haven't fired when tests run
**How to avoid:** Configure `theme: stadion` in WPLoader to activate the theme; CPTs register on `init`
**Warning signs:** `get_post_type_object('person')` returns null in tests

### Pitfall 6: REST Routes Not Available
**What goes wrong:** REST API dispatch returns 404 for custom endpoints
**Why it happens:** `rest_api_init` hook not fired before dispatching requests
**How to avoid:** Call `do_action('rest_api_init')` in setUp after creating WP_REST_Server
**Warning signs:** Routes work in browser but return `rest_no_route` in tests
</common_pitfalls>

<code_examples>
## Code Examples

Verified patterns from official sources:

### codeception.yml Configuration
```yaml
# Source: wp-browser docs + Stadion-specific adaptations
namespace: Tests
support_namespace: Support
actor_suffix: Tester
paths:
  tests: tests
  output: tests/_output
  support: tests/Support

extensions:
  enabled:
    - Codeception\Extension\RunFailed

suites:
  Wpunit:
    actor: WpunitTester
    path: Wpunit
    modules:
      enabled:
        - WPLoader
      config:
        WPLoader:
          wpRootFolder: "%WP_ROOT_FOLDER%"
          dbUrl: "%TEST_DB_URL%"
          tablePrefix: "wp_"
          domain: "%WP_DOMAIN%"
          adminEmail: "admin@stadion.test"
          plugins:
            - advanced-custom-fields-pro/acf.php
          theme: stadion
          loadOnly: false
          configFile: ""
```

### tests/.env.testing Template
```bash
# Source: wp-browser docs
# Copy to .env and update values for your environment

# Path to WordPress root (contains wp-load.php)
WP_ROOT_FOLDER=/Users/joostdevalk/Code/wordpress

# Test database (WILL BE DROPPED AND RECREATED)
TEST_DB_URL=mysql://root:password@localhost/stadion_test

# Domain for test requests
WP_DOMAIN=stadion.test
```

### Access Control Test
```php
<?php
// Source: WordPress core test patterns + Stadion access control logic
namespace Tests\Wpunit\AccessControl;

use Tests\Support\StadionTestCase;

class UserIsolationTest extends StadionTestCase {

    public function test_users_cannot_see_others_contacts(): void {
        // Arrange: Create two users with their own contacts
        $alice = self::factory()->user->create(['role' => 'stadion_user']);
        $bob = self::factory()->user->create(['role' => 'stadion_user']);

        wp_set_current_user($alice);
        $alice_contact = $this->createPerson(['post_title' => 'Alice Contact']);

        wp_set_current_user($bob);
        $bob_contact = $this->createPerson(['post_title' => 'Bob Contact']);

        // Act: Query all people as Bob
        $query = new \WP_Query([
            'post_type' => 'person',
            'posts_per_page' => -1,
        ]);

        // Assert: Bob only sees his own contact
        $this->assertEquals(1, $query->found_posts);
        $this->assertEquals($bob_contact, $query->posts[0]->ID);
    }

    public function test_admin_sees_own_data_in_frontend(): void {
        // Arrange: Create admin user and another user
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $user = self::factory()->user->create(['role' => 'stadion_user']);

        wp_set_current_user($user);
        $user_contact = $this->createPerson(['post_title' => 'User Contact']);

        // Act: Query as admin (frontend restrictions should apply)
        wp_set_current_user($admin);
        $query = new \WP_Query([
            'post_type' => 'person',
            'posts_per_page' => -1,
        ]);

        // Assert: Admin only sees their own (none in this case)
        // Based on Stadion access control: admins are restricted like regular users on frontend
        $this->assertEquals(0, $query->found_posts);
    }
}
```

### Factory Helper in Base Test Case
```php
<?php
// Source: wp-browser WPTestCase + ACF patterns
namespace Tests\Support;

use lucatume\WPBrowser\TestCase\WPTestCase;

abstract class StadionTestCase extends WPTestCase {

    /**
     * Create a person post with optional ACF fields.
     *
     * @param array $args Post arguments (post_title, post_author, etc.)
     * @param array $acf ACF field values keyed by field name
     * @return int Post ID
     */
    protected function createPerson(array $args = [], array $acf = []): int {
        $defaults = [
            'post_type'   => 'person',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];

        $post_id = self::factory()->post->create(array_merge($defaults, $args));

        // Set ACF fields if provided
        foreach ($acf as $field => $value) {
            update_field($field, $value, $post_id);
        }

        return $post_id;
    }

    /**
     * Create an organization (company) post.
     */
    protected function createOrganization(array $args = [], array $acf = []): int {
        $defaults = [
            'post_type'   => 'company',  // Post type slug
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];

        $post_id = self::factory()->post->create(array_merge($defaults, $args));

        foreach ($acf as $field => $value) {
            update_field($field, $value, $post_id);
        }

        return $post_id;
    }

    /**
     * Create a Stadion User (custom role).
     */
    protected function createStadionUser(array $args = []): int {
        $defaults = ['role' => 'stadion_user'];
        return self::factory()->user->create(array_merge($defaults, $args));
    }
}
```
</code_examples>

<sota_updates>
## State of the Art (2025-2026)

What's changed recently:

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| wp-browser v3 with Codeception 4 | wp-browser v4 with Codeception 5 | 2024 | PHP 8.0+ required, namespace changes |
| Manual test DB setup scripts | WPLoader automatic handling | v4 | Less configuration, better isolation |
| `$this->factory` | `self::factory()` | WordPress changeset 54087 | Static access, better IDE support |
| `setUp()` / `tearDown()` | `set_up()` / `tear_down()` | WordPress 5.9 | Snake_case for compatibility |
| WPBootstrapper module | Deprecated | v4 | Use WPLoader instead |

**New tools/patterns to consider:**
- **SQLite for fast tests:** wp-browser v4 supports SQLite Database Integration plugin for faster test runs; consider for unit tests where MySQL specifics don't matter
- **Parallel test execution:** Codeception supports parallel runs with `codecept run --shard`; useful for CI

**Deprecated/outdated:**
- **WPBootstrapper module:** Removed in v4, use WPLoader
- **Codeception\Command\* namespaces:** Moved to lucatume\WPBrowser\Command\*
- **`$this->factory`:** Use `self::factory()` static access
</sota_updates>

<open_questions>
## Open Questions

Things that couldn't be fully resolved:

1. **ACF Pro Composer Installation**
   - What we know: ACF Pro requires license key for Composer install, or manual download
   - What's unclear: Whether tests can activate ACF from `wp-content/plugins` outside the test WordPress
   - Recommendation: Configure WPLoader `wpRootFolder` to point to existing WP install where ACF Pro is already installed

2. **Test Database Location**
   - What we know: Need a dedicated `stadion_test` database that gets dropped/recreated
   - What's unclear: Should this be local MySQL or could use Docker for isolation?
   - Recommendation: Start with local MySQL (`stadion_test`), consider Docker for CI later

3. **Stadion User Role in Tests**
   - What we know: Theme registers `stadion_user` role on activation
   - What's unclear: Will WPLoader activation trigger role registration before tests run?
   - Recommendation: Verify role exists in first test; if not, investigate theme activation sequence
</open_questions>

<sources>
## Sources

### Primary (HIGH confidence)
- [wp-browser GitHub](https://github.com/lucatume/wp-browser) - 10 years proven, 631 stars, 0 open issues
- [wp-browser Documentation](https://wpbrowser.wptestkit.dev/) - Official docs for v4
- [WPLoader Module Docs](https://wpbrowser.wptestkit.dev/modules/WPLoader/) - Configuration reference
- [Packagist: lucatume/wp-browser](https://packagist.org/packages/lucatume/wp-browser) - Version 4.5.10 (2025-11-21)

### Secondary (MEDIUM confidence)
- [WordPress Core PHPUnit Docs](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/) - Factory patterns, assertions
- [WP_UnitTestCase Factory Docs](https://miya0001.github.io/wp-unit-docs/factory.html) - Factory method reference
- [wp-browser Migration Guide](https://wpbrowser.wptestkit.dev/migration/) - v3 to v4 changes
- [ACF Automated Testing Blog](https://www.advancedcustomfields.com/blog/wordpress-automated-testing/) - ACF-specific patterns

### Tertiary (LOW confidence - needs validation)
- GitHub Issues (#557, #716) - Known bugs with database drivers, $wpdb null
- Community examples - REST API testing patterns (verify against docs)
</sources>

<metadata>
## Metadata

**Research scope:**
- Core technology: wp-browser 4.x (Codeception 5.x + PHPUnit)
- Ecosystem: WPLoader module, WordPress test factories, ACF integration
- Patterns: Integration testing, REST API testing, access control testing
- Pitfalls: Database isolation, ACF activation, state leakage, CPT registration

**Confidence breakdown:**
- Standard stack: HIGH - verified with Packagist, official docs
- Architecture: HIGH - from official documentation and WordPress core patterns
- Pitfalls: HIGH - documented in GitHub issues and official troubleshooting
- Code examples: HIGH - adapted from official sources with Stadion-specific context

**Research date:** 2026-01-13
**Valid until:** 2026-02-13 (30 days - wp-browser ecosystem stable)
</metadata>

---

*Phase: 21-phpunit-setup*
*Research completed: 2026-01-13*
*Ready for planning: yes*
