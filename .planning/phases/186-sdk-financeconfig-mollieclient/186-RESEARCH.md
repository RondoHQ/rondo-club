# Phase 186: SDK Installation + FinanceConfig + MollieClient - Research

**Researched:** 2026-02-17
**Domain:** Composer package installation, WordPress Options API credential storage, PHP SDK wrapper class
**Confidence:** HIGH

## Summary

This is a backend-only foundation phase. It installs the Mollie PHP SDK via Composer, extends `FinanceConfig` with four new methods for Mollie API key and provider settings, and creates a thin `MollieClient` wrapper class. No REST routes are registered and no user-visible changes occur.

The existing codebase already contains every pattern this phase replicates: `CredentialEncryption` handles sodium encrypt/decrypt for secrets, `FinanceConfig` already stores Rabobank credentials using the same pattern, and `functions.php` already imports `Rondo\Finance\RabobankOAuth` and `Rondo\Finance\RabobankPayment` in the REST-only block. The work is additive and mirrors established patterns exactly.

The only novel concern is Composer dependency resolution — `mollie/mollie-api-php ^3.9` pulls in `nyholm/psr7 ^1.8` as the sole new transitive dependency. All other PSR HTTP dependencies (`psr/http-client`, `psr/http-factory`, `psr/http-message`) are already installed by `google/apiclient`. Guzzle conflicts are not a risk for v3+ of the Mollie SDK.

**Primary recommendation:** Run `composer require mollie/mollie-api-php:^3.9` from the `rondo-club/` directory, verify `composer install` succeeds with no conflicts, then extend `FinanceConfig` and create `class-mollie-client.php` following the Rabobank patterns exactly.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `mollie/mollie-api-php` | `^3.9` | Official Mollie PHP SDK — API key auth, payments, status lookup | Only official PHP SDK; v3 is the current major; no alternative exists |
| `nyholm/psr7` | `^1.8` | PSR-7 HTTP message implementation pulled in by Mollie SDK | New transitive dependency pulled in automatically; no conflicts |

### Already Installed (no additions needed)
| Library | Status | Notes |
|---------|--------|-------|
| `psr/http-client ^1.0` | Installed via `google/apiclient` | Mollie SDK requires same range |
| `psr/http-factory ^1.1` | Installed | Same |
| `psr/http-message ^1.1\|^2.0` | Installed | Same |
| `guzzlehttp/guzzle 7.10.0` | Installed | Mollie v3 uses Guzzle only as `require-dev`; no conflict |

**Installation:**
```bash
# From rondo-club/ directory
composer require mollie/mollie-api-php:^3.9
```

This updates `composer.json` and `composer.lock`. After deploying, run `composer install` on the server (the deploy script handles file sync; a post-deploy `composer install` step is needed if `vendor/` is not committed).

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-finance-config.php          # MODIFIED: + Mollie methods + active_provider
├── class-mollie-client.php           # NEW: thin SDK wrapper
├── class-rabobank-oauth.php          # unchanged
├── class-rabobank-payment.php        # unchanged
└── class-credential-encryption.php  # unchanged

composer.json                         # MODIFIED: + mollie/mollie-api-php ^3.9
functions.php                         # MODIFIED: + use Rondo\Finance\MollieClient import
```

### Pattern 1: FinanceConfig — Adding New Option Constants and Methods

**What:** Append four new option key constants and four new methods to the existing `FinanceConfig` class. Also extend `get_all_settings()` to include `mollie_has_api_key` and `mollie_environment`, and extend `update_settings()` to handle the `mollie_api_key` key.

**When to use:** Exactly mirrors the existing Rabobank pattern in the same file.

**Existing Rabobank pattern to follow:**

From `class-finance-config.php` (lines 34, 163-171, 204-206, 298-312, 325-334):
```php
// Existing constant pattern:
const OPTION_RABOBANK_CREDENTIALS = 'rondo_finance_rabobank_credentials';

// Existing getter pattern:
public function get_rabobank_credentials(): ?array {
    $encrypted = get_option( self::OPTION_RABOBANK_CREDENTIALS, '' );
    if ( empty( $encrypted ) ) {
        return null;
    }
    return CredentialEncryption::decrypt( $encrypted );
}

// Existing get_all_settings() pattern (safe representation):
'rabobank_has_credentials' => $rabobank_creds !== null,
'rabobank_environment'     => $rabobank_creds['environment'] ?? '',

// Existing update_settings() handler:
if ( isset( $data['rabobank_client_id'] ) && isset( $data['rabobank_client_secret'] ) && isset( $data['rabobank_environment'] ) ) {
    $success = $this->update_rabobank_credentials( ... ) && $success;
}

// Existing update method:
public function update_rabobank_credentials( string $client_id, string $client_secret, string $environment ): bool {
    $credentials = [ 'client_id' => $client_id, 'client_secret' => $client_secret, 'environment' => $environment ];
    $encrypted = CredentialEncryption::encrypt( $credentials );
    return update_option( self::OPTION_RABOBANK_CREDENTIALS, $encrypted );
}
```

**New Mollie additions to mirror this pattern:**
```php
// New constants (add after OPTION_BCC_EMAIL):
const OPTION_MOLLIE_API_KEY           = 'rondo_finance_mollie_api_key';
const OPTION_ACTIVE_PAYMENT_PROVIDER  = 'rondo_finance_active_payment_provider';

// New getter — returns decrypted key for internal use:
public function get_mollie_api_key(): string {
    $encrypted = get_option( self::OPTION_MOLLIE_API_KEY, '' );
    if ( empty( $encrypted ) ) {
        return '';
    }
    $data = CredentialEncryption::decrypt( $encrypted );
    return $data['api_key'] ?? '';
}

// New updater — encrypts before storage:
public function update_mollie_api_key( string $api_key ): bool {
    if ( empty( $api_key ) ) {
        return delete_option( self::OPTION_MOLLIE_API_KEY );
    }
    $encrypted = CredentialEncryption::encrypt( [ 'api_key' => $api_key ] );
    return update_option( self::OPTION_MOLLIE_API_KEY, $encrypted );
}

// Active provider getter — 'rabobank' is the safe default:
public function get_active_payment_provider(): string {
    return get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
}

// Active provider updater:
public function update_active_payment_provider( string $provider ): bool {
    $allowed = [ 'rabobank', 'mollie' ];
    if ( ! in_array( $provider, $allowed, true ) ) {
        return false;
    }
    return update_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, $provider );
}
```

**Additions to `get_all_settings()`:**
```php
// Add after 'rabobank_environment' key:
$mollie_api_key = $this->get_mollie_api_key();
// ...
'mollie_has_api_key'       => ! empty( $mollie_api_key ),
'mollie_environment'       => $this->derive_mollie_environment( $mollie_api_key ),
'active_payment_provider'  => $this->get_active_payment_provider(),
```

**Helper method for environment derivation:**
```php
private function derive_mollie_environment( string $api_key ): string {
    if ( empty( $api_key ) ) {
        return '';
    }
    return str_starts_with( $api_key, 'live_' ) ? 'live' : 'test';
}
```

**Additions to `update_settings()`:**
```php
// Handle Mollie API key with encryption:
if ( isset( $data['mollie_api_key'] ) ) {
    $success = $this->update_mollie_api_key(
        sanitize_text_field( $data['mollie_api_key'] )
    ) && $success;
}

// Handle active payment provider:
if ( isset( $data['active_payment_provider'] ) ) {
    $success = $this->update_active_payment_provider(
        sanitize_text_field( $data['active_payment_provider'] )
    ) && $success;
}
```

### Pattern 2: CredentialEncryption Usage

**What:** `CredentialEncryption::encrypt()` takes an array, JSON-encodes it, encrypts with sodium, returns base64. `decrypt()` reverses it and returns the array or null. The Mollie API key is stored as `['api_key' => $api_key]` — wrapped in an array, consistent with how Rabobank stores `['client_id' => ..., 'client_secret' => ..., 'environment' => ...]`.

**Critical detail:** `CredentialEncryption` lives in namespace `Rondo\Data` and is already imported at the top of `class-finance-config.php` as `use Rondo\Data\CredentialEncryption;`. No new imports needed.

From `class-credential-encryption.php`:
```php
// Encrypt: takes array, returns base64 string
public static function encrypt( array $data ): string {
    $json       = wp_json_encode( $data );
    $nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
    $ciphertext = sodium_crypto_secretbox( $json, $nonce, self::get_key() );
    return base64_encode( $nonce . $ciphertext );
}

// Decrypt: takes base64 string, returns array|null
public static function decrypt( string $encrypted ): ?array {
    // ... returns null on any failure, array on success
}
```

### Pattern 3: MollieClient — Thin SDK Wrapper

**What:** New file `includes/class-mollie-client.php`. Namespace `Rondo\Finance`. Constructor reads the decrypted API key from `FinanceConfig`, creates `MollieApiClient`, calls `setApiKey()`. Public method `get()` returns the configured client.

**When to use:** Both `MolliePayment` (Phase 187) and `MollieWebhook` (Phase 188) instantiate `new MollieClient()` to get a configured SDK client. No singleton — each instantiation reads fresh from `FinanceConfig`.

```php
<?php
/**
 * Mollie Client Wrapper
 *
 * Provides a configured MollieApiClient instance to both MolliePayment
 * and MollieWebhook without duplicating API key setup logic.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Mollie\Api\MollieApiClient;
use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MollieClient {

    private MollieApiClient $client;

    /**
     * Constructor — reads API key from FinanceConfig and initializes the SDK client.
     *
     * @throws \Mollie\Api\Exceptions\ApiException if API key is invalid
     */
    public function __construct() {
        $config  = new FinanceConfig();
        $api_key = $config->get_mollie_api_key();

        $this->client = new MollieApiClient();
        $this->client->setApiKey( $api_key );
    }

    /**
     * Get the configured Mollie API client.
     *
     * @return MollieApiClient
     */
    public function get(): MollieApiClient {
        return $this->client;
    }
}
```

### Pattern 4: functions.php — Adding use Import Only

**What:** Phase 186 does NOT instantiate `MollieClient` in `rondo_init()`. `MollieClient` is not instantiated directly by `functions.php` — it is instantiated by `MolliePayment` and `MollieWebhook` (Phases 187 and 188). Phase 186 only needs to add the `use` import at the top of `functions.php` so later phases can reference `MollieClient` without autoloader issues.

**Note:** Actually, since Composer PSR-4 autoloading handles `Rondo\Finance\MollieClient` automatically (the autoload map covers all of `includes/`), no manual `use` import in `functions.php` is strictly required for autoloading to work. However, for consistency with the existing pattern where all used classes are declared at the top with `use` statements, add the import when `MollieClient` is first used — which is Phase 187 when `MolliePayment` is added.

**Conclusion for Phase 186:** `functions.php` does NOT need modification in this phase. The class will be autoloaded when needed. The import will be added in Phase 187 when `MolliePayment` is instantiated in the REST block.

### Anti-Patterns to Avoid

- **Storing the raw API key in plain text:** Use `CredentialEncryption::encrypt(['api_key' => $api_key])` — never `update_option('rondo_finance_mollie_api_key', $api_key)` directly.
- **Exposing the raw API key via `get_all_settings()`:** Return `mollie_has_api_key` (bool) and `mollie_environment` (string), never the key itself. Same pattern as Rabobank (`rabobank_has_credentials`, `rabobank_environment`).
- **Returning `null` from `get_mollie_api_key()` when empty:** Return `''` (empty string) so callers can do simple `empty()` checks without null checks. Consistent with all other string getters in `FinanceConfig`.
- **Using `mollie/mollie-api-php` v2 patterns:** v3 removed the fluent API. `$mollie->setApiKey()` still works for initialization; `$mollie->payments->create()` still works in v3 as the array-based create method. Check installed version after `composer require`.
- **Adding `MollieClient` to `rondo_init()` in this phase:** No instantiation in `functions.php` this phase — the class is only used by `MolliePayment` and `MollieWebhook` which are created in Phases 187–188.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Sodium encrypt/decrypt for Mollie key | Custom crypto | `CredentialEncryption::encrypt/decrypt` | Already exists, tested, handles nonce + ciphertext correctly |
| HTTP client for Mollie API | `wp_remote_post()` calls | `mollie/mollie-api-php` SDK | SDK handles Bearer auth, response hydration, PSR HTTP adapter |
| Mode detection (test/live) | Separate environment toggle/option | Derive from key prefix (`test_` vs `live_`) | Mollie's key prefix is authoritative; a separate toggle creates sync risk |

**Key insight:** The entire crypto stack, HTTP layer, and response parsing are handled by existing code. Phase 186 is pure configuration wiring.

## Common Pitfalls

### Pitfall 1: Composer Dependency Conflicts

**What goes wrong:** `composer require mollie/mollie-api-php:^3.9` fails due to PSR interface version conflicts with existing packages.

**Why it happens:** Both `nyholm/psr7` and `guzzlehttp/psr7` implement `psr/http-message`. Composer uses virtual packages (`psr/http-message-implementation`) to resolve this — it is standard and does not conflict. Guzzle itself is only in Mollie's `require-dev`, not production requirements.

**How to avoid:** Run `composer require mollie/mollie-api-php:^3.9` and verify it exits 0. If conflicts appear, run `composer why-not mollie/mollie-api-php` to diagnose. The Mollie v3 Guzzle conflict is a v2 problem — v3 is clean.

**Warning signs:** `composer require` exits with a non-zero code, or `composer install` on the server prints conflict messages.

### Pitfall 2: Decrypting Mollie Key Returns Null

**What goes wrong:** `get_mollie_api_key()` returns `''` even after a key has been stored, because `CredentialEncryption::decrypt()` returns `null`.

**Why it happens:** Sodium requires the same `AUTH_KEY` for encrypt and decrypt. If the WordPress `AUTH_KEY` changes (e.g., a new install, or a copy from dev to prod without migrating secrets), decryption fails. Also happens if the option is corrupted.

**How to avoid:** After storing a key via `update_mollie_api_key()`, immediately read it back with `get_mollie_api_key()` in verification. The option must survive a round-trip.

**Warning signs:** `update_mollie_api_key('test_xxx')` returns `true` but subsequent `get_mollie_api_key()` returns `''`.

### Pitfall 3: `get_all_settings()` Exposes the Raw Key

**What goes wrong:** The Finance Settings REST endpoint returns the Mollie API key in plain text in the JSON response.

**Why it happens:** Accidentally adding `'mollie_api_key' => $this->get_mollie_api_key()` to `get_all_settings()` instead of the safe representation.

**How to avoid:** Follow the exact Rabobank pattern: only expose `mollie_has_api_key` (bool) and `mollie_environment` (string). The raw key is only decrypted in `get_mollie_api_key()` for internal use by `MollieClient`.

### Pitfall 4: Default Provider Not Defaulting to 'rabobank'

**What goes wrong:** `get_active_payment_provider()` returns an unexpected value (e.g., `false` or `null`) when the option has never been set.

**Why it happens:** `get_option()` returns `false` for unset options. Forgetting the default parameter.

**How to avoid:** Always `get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' )` — the second argument is the default. Existing Rabobank behavior must remain completely unchanged until Mollie is explicitly configured.

## Code Examples

### FinanceConfig — Complete New Methods

```php
// Source: Modeled on get_rabobank_credentials() in class-finance-config.php

const OPTION_MOLLIE_API_KEY          = 'rondo_finance_mollie_api_key';
const OPTION_ACTIVE_PAYMENT_PROVIDER = 'rondo_finance_active_payment_provider';

/**
 * Get Mollie API key (decrypted, internal use only)
 *
 * @return string Decrypted API key, or empty string if not configured
 */
public function get_mollie_api_key(): string {
    $encrypted = get_option( self::OPTION_MOLLIE_API_KEY, '' );
    if ( empty( $encrypted ) ) {
        return '';
    }
    $data = CredentialEncryption::decrypt( $encrypted );
    return $data['api_key'] ?? '';
}

/**
 * Update Mollie API key (encrypts before storage)
 *
 * @param string $api_key Mollie API key (test_ or live_ prefix)
 * @return bool True on success
 */
public function update_mollie_api_key( string $api_key ): bool {
    if ( empty( $api_key ) ) {
        return (bool) delete_option( self::OPTION_MOLLIE_API_KEY );
    }
    $encrypted = CredentialEncryption::encrypt( [ 'api_key' => $api_key ] );
    return update_option( self::OPTION_MOLLIE_API_KEY, $encrypted );
}

/**
 * Get active payment provider
 *
 * @return string 'rabobank' (default) or 'mollie'
 */
public function get_active_payment_provider(): string {
    return get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
}

/**
 * Update active payment provider
 *
 * @param string $provider 'rabobank' or 'mollie'
 * @return bool True on success, false if invalid provider
 */
public function update_active_payment_provider( string $provider ): bool {
    $allowed = [ 'rabobank', 'mollie' ];
    if ( ! in_array( $provider, $allowed, true ) ) {
        return false;
    }
    return update_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, $provider );
}

/**
 * Derive Mollie environment from API key prefix
 *
 * @param string $api_key Mollie API key
 * @return string 'live', 'test', or '' (empty if no key)
 */
private function derive_mollie_environment( string $api_key ): string {
    if ( empty( $api_key ) ) {
        return '';
    }
    return str_starts_with( $api_key, 'live_' ) ? 'live' : 'test';
}
```

### get_all_settings() additions

```php
// Source: Modeled on rabobank_has_credentials pattern in class-finance-config.php

// In get_all_settings(), after rabobank entries:
$mollie_api_key = $this->get_mollie_api_key();

return [
    // ... existing fields ...
    'rabobank_has_credentials'  => $rabobank_creds !== null,
    'rabobank_environment'      => $rabobank_creds['environment'] ?? '',
    // NEW:
    'mollie_has_api_key'        => ! empty( $mollie_api_key ),
    'mollie_environment'        => $this->derive_mollie_environment( $mollie_api_key ),
    'active_payment_provider'   => $this->get_active_payment_provider(),
];
```

### update_settings() additions

```php
// Source: Modeled on rabobank handler in class-finance-config.php update_settings()

// In update_settings(), after bcc_email handling:
if ( isset( $data['mollie_api_key'] ) ) {
    $success = $this->update_mollie_api_key(
        sanitize_text_field( $data['mollie_api_key'] )
    ) && $success;
}

if ( isset( $data['active_payment_provider'] ) ) {
    $success = $this->update_active_payment_provider(
        sanitize_text_field( $data['active_payment_provider'] )
    ) && $success;
}
```

### MollieClient — Complete Class

```php
// Source: Pattern from class-rabobank-oauth.php and ARCHITECTURE.md

<?php
/**
 * Mollie Client Wrapper
 *
 * Initialises the Mollie PHP SDK with the stored API key, providing
 * a configured MollieApiClient to MolliePayment and MollieWebhook.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Mollie\Api\MollieApiClient;
use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thin wrapper around MollieApiClient.
 *
 * Not a singleton — each instantiation reads fresh from FinanceConfig.
 * These are short-lived per-request objects; the overhead is negligible.
 */
class MollieClient {

    /**
     * @var MollieApiClient
     */
    private MollieApiClient $client;

    /**
     * Constructor — reads API key and configures the SDK client.
     *
     * @throws \Mollie\Api\Exceptions\ApiException if the stored API key is rejected by Mollie.
     */
    public function __construct() {
        $config  = new FinanceConfig();
        $api_key = $config->get_mollie_api_key();

        $this->client = new MollieApiClient();
        $this->client->setApiKey( $api_key );
    }

    /**
     * Get the configured Mollie API client.
     *
     * @return MollieApiClient
     */
    public function get(): MollieApiClient {
        return $this->client;
    }
}
```

## Exact Files to Create or Modify

### 1. `composer.json` — Add Mollie SDK

Run `composer require mollie/mollie-api-php:^3.9` from `rondo-club/`. This command updates `composer.json` and `composer.lock` automatically. Do NOT hand-edit `composer.json` — let Composer manage the version constraints.

After running the command, verify `composer.json` has:
```json
"require": {
    "php": ">=8.0",
    "google/apiclient": "^2.15",
    "mollie/mollie-api-php": "^3.9",
    "mpdf/mpdf": "^8.2",
    "sabre/dav": "^4.6"
}
```

### 2. `includes/class-finance-config.php` — Extend (do NOT rewrite)

Minimal additions to the existing file:
- Add 2 new constants after `OPTION_BCC_EMAIL` (line 37)
- Add 4 new public methods + 1 private helper after `update_rabobank_credentials()`
- Extend `get_all_settings()` return array with 3 new keys
- Extend `update_settings()` with 2 new `isset()` blocks

### 3. `includes/class-mollie-client.php` — New file

Create from scratch using the code example above. Namespace `Rondo\Finance`. File naming convention matches existing: `class-mollie-client.php`.

### 4. `functions.php` — No changes in this phase

`MollieClient` is autoloaded by Composer PSR-4 (`Rondo\\` maps to `includes/`). No manual `require_once` needed. The `use` import and instantiation happen in Phase 187 when `MolliePayment` is added.

## Verification Steps

After implementation, verify these before marking the phase complete:

1. **Composer installs without errors:**
   ```bash
   composer install --no-dev 2>&1
   # Must exit 0, no conflicts
   ```

2. **Autoload works:** `class_exists('Mollie\Api\MollieApiClient')` returns true after `require_once vendor/autoload.php`.

3. **`FinanceConfig` methods exist and round-trip correctly:**
   ```php
   $config = new \Rondo\Config\FinanceConfig();
   $config->update_mollie_api_key('test_abc123');
   assert($config->get_mollie_api_key() === 'test_abc123');
   assert($config->get_active_payment_provider() === 'rabobank'); // default
   $config->update_active_payment_provider('mollie');
   assert($config->get_active_payment_provider() === 'mollie');
   ```

4. **`get_all_settings()` never exposes the raw key:**
   ```php
   $settings = $config->get_all_settings();
   assert(isset($settings['mollie_has_api_key']));    // bool
   assert(isset($settings['mollie_environment']));    // 'test'
   assert(isset($settings['active_payment_provider'])); // 'rabobank'|'mollie'
   assert(!isset($settings['mollie_api_key']));       // must NOT exist
   ```

5. **`MollieClient` initializes without fatal errors** (requires a valid test API key stored):
   ```php
   $client = new \Rondo\Finance\MollieClient();
   // No exception thrown = SDK initialized correctly
   ```

6. **No user-visible changes on the site** — this is verified by deploying and confirming the Finance Settings page renders identically to before.

## Open Questions

1. **Deploy step for `vendor/` update**
   - What we know: `bin/deploy.sh` syncs theme files but excludes `node_modules`; the script does not reference `vendor/`.
   - What's unclear: Does the deploy script sync `vendor/` to the server, or does it assume `composer install` is run separately on the server after deploy?
   - Recommendation: Check `bin/deploy.sh` to confirm `vendor/` is included in the rsync. If not, the planner must add a post-deploy `composer install` step on the server. This is critical — the Mollie SDK classes must be present in `vendor/` on the server for autoloading to work.

2. **`str_starts_with()` availability**
   - What we know: PHP 8.0+ introduced `str_starts_with()`. The `composer.json` requires `php: >=8.0`.
   - What's unclear: Whether the production server runs PHP 8.0+.
   - Recommendation: Use `str_starts_with()` — it matches the existing PHP version requirement. If there's any doubt, use `strpos($api_key, 'live_') === 0` as a safe fallback.

## Sources

### Primary (HIGH confidence)
- `includes/class-finance-config.php` — authoritative for FinanceConfig patterns, existing constants, method signatures, `update_settings()` structure
- `includes/class-credential-encryption.php` — authoritative for `encrypt(array): string` and `decrypt(string): ?array` signatures
- `composer.json` — current state: no Mollie dependency, PHP `>=8.0`, existing PSR dependencies via `google/apiclient`
- `functions.php` — authoritative for REST-only instantiation block (lines 363-378), `use` import pattern, `Rondo\Finance\*` namespace usage
- `.planning/research/SUMMARY.md` — executive summary of milestone-level research, phase 186 deliverables
- `.planning/research/STACK.md` — Composer dependency analysis, version compatibility, installation command
- `.planning/research/ARCHITECTURE.md` — `FinanceConfig` additions, `MollieClient` code pattern, option key names

### Secondary (MEDIUM confidence)
- [Packagist: mollie/mollie-api-php](https://packagist.org/packages/mollie/mollie-api-php) — v3.9.0 released 2026-02-09, PHP >=7.4, transitive dependencies verified (from milestone research)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — composer.json current state verified directly; Mollie SDK version from milestone research verified on Packagist
- Architecture: HIGH — all patterns read directly from live codebase files; no inference needed
- Pitfalls: HIGH — derived from direct codebase reading + milestone research on sodium encryption and WordPress Options API

**Research date:** 2026-02-17
**Valid until:** 2026-03-17 (stable patterns; Composer dep versions may shift but `^3.9` constraint is safe)
