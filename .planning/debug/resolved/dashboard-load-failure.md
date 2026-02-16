---
status: resolved
trigger: "dashboard-load-failure"
created: 2026-02-16T00:00:00Z
updated: 2026-02-16T01:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - Namespace collision in CommentTypes class
test: Add global namespace prefix to register_comment_meta() calls
expecting: Dashboard will load after fixing namespace references
next_action: Fix class-comment-types.php and redeploy

## Symptoms

expected: Dashboard loads normally at https://rondo.svawc.nl/
actual: Error message "Dashboard data kon niet worden geladen — Controleer je verbinding en ververs de pagina."
errors: The Dutch error message translates to "Dashboard data could not be loaded — Check your connection and refresh the page." This is a frontend error shown when the dashboard API call fails.
reproduction: Visit the dashboard at rondo.svawc.nl after Phase 180 deployment
started: Immediately after deploying Phase 180 (Invoice Creation Flow). The deployment included new PHP classes for invoice REST endpoints, invoice numbering service, and modifications to existing files. The issue did NOT exist before this deployment.

## Eliminated

- hypothesis: Composer autoload classmap not regenerated after adding new classes
  evidence: Production server's autoload_classmap.php DID contain InvoiceNumbering and FinanceConfig classes. Deploy script runs composer dump-autoload on server after syncing files. Classes could be instantiated via wp-cli without error.
  timestamp: 2026-02-16T00:45:00Z

## Evidence

- timestamp: 2026-02-16T00:10:00Z
  checked: Production error log attempt
  found: No debug.log file found, WordPress showing generic "critical error" page
  implication: PHP fatal error is occurring but logs aren't accessible

- timestamp: 2026-02-16T00:15:00Z
  checked: PHP syntax validation of modified files
  found: All files (class-rest-invoices.php, class-invoice-numbering.php, functions.php) have valid PHP syntax
  implication: Not a syntax error

- timestamp: 2026-02-16T00:20:00Z
  checked: Composer autoload classmap on production
  found: Both FinanceConfig and InvoiceNumbering ARE registered in vendor/composer/autoload_classmap.php
  implication: Classes should be autoloadable

- timestamp: 2026-02-16T00:25:00Z
  checked: REST API endpoint response
  found: WordPress returns HTML error page "There has been a critical error" instead of JSON
  implication: PHP fatal error during REST API initialization, before route is reached

- timestamp: 2026-02-16T00:30:00Z
  checked: Local composer autoload classmap
  found: LOCAL autoload classmap was MISSING the new classes (InvoiceNumbering, FinanceConfig)
  implication: Autoload wasn't regenerated after Phase 179/180 added new class files

- timestamp: 2026-02-16T00:32:00Z
  checked: Ran composer dump-autoload -o locally
  found: Generated optimized autoload with 35820 classes, new classes now present
  implication: WRONG HYPOTHESIS - production autoload was already correct

- timestamp: 2026-02-16T00:45:00Z
  checked: Created debug script on server to capture fatal error during rest_api_init
  found: Fatal error "Call to undefined function Rondo\Collaboration\register_comment_meta() in class-comment-types.php:55"
  implication: Namespace collision - method register_comment_meta() shadows WordPress global function

- timestamp: 2026-02-16T00:50:00Z
  checked: Examined class-comment-types.php lines 36-63
  found: Class has method register_comment_meta() and calls register_comment_meta() without \ prefix
  implication: PHP looks for Rondo\Collaboration\register_comment_meta() function, not global \register_comment_meta()

## Resolution

root_cause: Namespace collision in class-comment-types.php. The CommentTypes class in namespace Rondo\Collaboration has a method named register_comment_meta() which shadows the WordPress global function register_comment_meta(). Inside the method body, calls to register_comment_meta() without the global namespace prefix (\) cause PHP to look for Rondo\Collaboration\register_comment_meta() function, which doesn't exist, resulting in a fatal error during rest_api_init. This breaks all REST API requests including the dashboard.

fix: Add global namespace prefix (\) to all register_comment_meta() calls inside the method, or rename the method to avoid the naming collision.

verification: PASSED
  - Dashboard REST API (/wp-json/rondo/v1/dashboard): Returns JSON 401 auth error (expected, not authenticated)
  - WordPress core REST API (/wp-json/): Returns full JSON route listing
  - Homepage (/): Loads React app with rondoConfig, no critical error
  - Fix confirmed: All REST API requests now work correctly
files_changed:
  - includes/class-comment-types.php
