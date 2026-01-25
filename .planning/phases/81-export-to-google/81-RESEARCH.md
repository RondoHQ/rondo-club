# Phase 81: Export to Google - Research

**Researched:** 2026-01-17
**Domain:** Google People API contact export, WordPress async processing
**Confidence:** HIGH

## Summary

This phase implements exporting Stadion contacts to Google Contacts via the People API. The codebase already has the Google API PHP client library installed, OAuth integration with read/write scope support, and established patterns for both async processing (WP-Cron single events) and save_post hooks.

The export functionality requires:
1. Reverse field mapping from Stadion ACF fields to Google Person objects
2. Creating new contacts via `people->createContact()`
3. Updating existing linked contacts via `people->updateContact()` with etag validation
4. Uploading photos via `people->updateContactPhoto()` with base64-encoded bytes
5. Queue-based async processing triggered by save_post hook

**Primary recommendation:** Use `wp_schedule_single_event()` with `spawn_cron()` for immediate async processing (matching the calendar rematch pattern), store export queue in post meta, and leverage the existing GoogleOAuth and GoogleContactsConnection classes.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| google/apiclient-services | Already installed | PeopleService for contact CRUD | Official Google SDK, already in vendor/ |
| google/apiclient | Already installed | OAuth client handling | Standard for Google API auth |
| WordPress WP-Cron | Core | Async background processing | Native WordPress, no dependencies |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| GoogleOAuth class | Existing | Token management, refresh | For all People API calls |
| GoogleContactsConnection | Existing | Connection state storage | Check user has readwrite access |
| CredentialEncryption | Existing | Secure token storage | For encrypted credential access |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP-Cron single events | Action Scheduler plugin | More robust queuing but adds dependency |
| Post meta queue | Custom table | Better performance but violates codebase rules |
| Sync processing | Async queue | Faster feedback but blocks user |

**Installation:**
No new packages required - all dependencies already installed.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-google-contacts-export.php      # Export logic (mirror of import class)
├── class-rest-google-contacts.php        # Add export endpoint (existing file)
└── class-google-oauth.php                # Existing OAuth (no changes)
```

### Pattern 1: Async Export via WP-Cron Single Event
**What:** Queue exports via `wp_schedule_single_event()` then `spawn_cron()` for immediate execution
**When to use:** On save_post hook to avoid blocking contact saves
**Example:**
```php
// Source: existing pattern in includes/class-auto-title.php lines 279-298
private function queue_export( int $post_id ): void {
    static $queued = [];

    // Prevent duplicate scheduling in same request
    if ( isset( $queued[ $post_id ] ) ) {
        return;
    }
    $queued[ $post_id ] = true;

    // Clear any existing scheduled event
    $timestamp = wp_next_scheduled( 'stadion_google_contact_export', [ $post_id ] );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'stadion_google_contact_export', [ $post_id ] );
    }

    // Schedule immediate execution
    wp_schedule_single_event( time(), 'stadion_google_contact_export', [ $post_id ] );
    spawn_cron();
}
```

### Pattern 2: Create/Update Decision Based on google_contact_id
**What:** Check for existing `_google_contact_id` meta to decide create vs update
**When to use:** When processing export queue
**Example:**
```php
// Decision logic for export
$google_contact_id = get_post_meta( $post_id, '_google_contact_id', true );

if ( $google_contact_id ) {
    // Contact was imported - update existing Google contact
    $this->update_google_contact( $post_id, $google_contact_id );
} else {
    // New Stadion contact - create in Google
    $resource_name = $this->create_google_contact( $post_id );
    // Store the returned resourceName for future syncs
    update_post_meta( $post_id, '_google_contact_id', $resource_name );
}
```

### Pattern 3: Etag-Based Update Validation
**What:** Google requires etag in update requests to prevent concurrent modification issues
**When to use:** When updating existing Google contacts
**Example:**
```php
// Source: Google People API documentation
// The etag must match or Google returns 400 error
$person->setMetadata( $this->build_metadata_with_etag( $google_contact_id, $etag ) );
$updated = $service->people->updateContact(
    $google_contact_id,
    $person,
    [
        'updatePersonFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations',
        'personFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations,metadata'
    ]
);
// Store new etag for next update
update_post_meta( $post_id, '_google_etag', $updated->getEtag() );
```

### Anti-Patterns to Avoid
- **Sync processing on save_post:** Blocks user, causes timeouts on slow Google API
- **Not checking readwrite scope:** User may only have readonly access
- **Ignoring etag:** Causes 400 errors on update
- **Multiple values for singleton fields:** Google rejects names, birthdays, genders with >1 value

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth token refresh | Custom refresh logic | `GoogleOAuth::get_access_token()` | Already handles refresh, encryption |
| Credential encryption | Plain storage | `CredentialEncryption::encrypt/decrypt()` | Secure, consistent with existing code |
| Field mapping | Ad-hoc conversion | Centralized mapper class | DRY - import already has field knowledge |
| Photo base64 encoding | Manual encoding | `base64_encode(file_get_contents())` | PHP native, straightforward |
| User connection check | Raw meta query | `GoogleContactsConnection::is_connected()` | Consistent API |

**Key insight:** The import class (`GoogleContactsAPI`) already has the field knowledge - the export should mirror its structure for consistency.

## Common Pitfalls

### Pitfall 1: Singleton Field Violations
**What goes wrong:** Sending multiple names, birthdays, or genders causes 400 error
**Why it happens:** Stadion might have repeater data, Google only accepts single values
**How to avoid:** Only send first/primary value for singleton fields
**Warning signs:** 400 error with "more than one field specified on singleton"

### Pitfall 2: Missing Etag on Updates
**What goes wrong:** Update request returns 400 with "failedPrecondition"
**Why it happens:** Contact was modified since last read, etag is stale or missing
**How to avoid:** Always include current etag in `person.metadata.sources`; on conflict, re-fetch contact and retry
**Warning signs:** 400 error with reason "failedPrecondition"

### Pitfall 3: Readonly Scope on Export
**What goes wrong:** Export fails with permission error
**Why it happens:** User connected with readonly scope, not readwrite
**How to avoid:** Check `GoogleOAuth::get_contacts_access_mode()` returns 'readwrite' before export
**Warning signs:** 403 permission denied errors

### Pitfall 4: Photo Size Limits
**What goes wrong:** Photo upload fails or is rejected
**Why it happens:** Image too large or wrong format
**How to avoid:** Resize images before encoding, use standard formats (JPEG/PNG)
**Warning signs:** Timeout or error on photo upload

### Pitfall 5: Sequential Request Requirement
**What goes wrong:** Batch exports fail with race conditions
**Why it happens:** Google recommends sequential mutations for same user
**How to avoid:** Process export queue one contact at a time, not parallel
**Warning signs:** Intermittent failures, "increased latency" errors

## Code Examples

Verified patterns from official sources and existing codebase:

### Creating a Google Contact
```php
// Source: google-api-php-client documentation, Google People API docs
$service = new \Google\Service\PeopleService( $client );

$person = new \Google\Service\PeopleService\Person();

// Name (singleton - only one allowed)
$name = new \Google\Service\PeopleService\Name();
$name->setGivenName( $first_name );
$name->setFamilyName( $last_name );
$person->setNames( [ $name ] );

// Email addresses (multiple allowed)
$emails = [];
foreach ( $contact_info as $info ) {
    if ( $info['contact_type'] === 'email' ) {
        $email = new \Google\Service\PeopleService\EmailAddress();
        $email->setValue( $info['contact_value'] );
        $email->setType( $info['contact_label'] ?: 'other' );
        $emails[] = $email;
    }
}
$person->setEmailAddresses( $emails );

// Phone numbers (multiple allowed)
$phones = [];
foreach ( $contact_info as $info ) {
    if ( in_array( $info['contact_type'], [ 'phone', 'mobile' ] ) ) {
        $phone = new \Google\Service\PeopleService\PhoneNumber();
        $phone->setValue( $info['contact_value'] );
        $phone->setType( $info['contact_type'] === 'mobile' ? 'mobile' : 'home' );
        $phones[] = $phone;
    }
}
$person->setPhoneNumbers( $phones );

// Create contact
$created = $service->people->createContact( $person, [
    'personFields' => 'names,emailAddresses,phoneNumbers,metadata'
]);

// Store resourceName and etag
$resource_name = $created->getResourceName(); // e.g., "people/c1234567890"
$etag = $created->getEtag();
```

### Updating an Existing Google Contact
```php
// Source: Google People API documentation
// CRITICAL: Must include metadata with etag
$service = new \Google\Service\PeopleService( $client );

$person = new \Google\Service\PeopleService\Person();

// Set resource name (required for update)
$person->setResourceName( $google_contact_id ); // e.g., "people/c1234567890"

// Metadata with etag (required to prevent concurrent modification)
$metadata = new \Google\Service\PeopleService\PersonMetadata();
$source = new \Google\Service\PeopleService\Source();
$source->setType( 'CONTACT' );
$source->setEtag( $stored_etag );
$metadata->setSources( [ $source ] );
$person->setMetadata( $metadata );

// Set fields to update...
$name = new \Google\Service\PeopleService\Name();
$name->setGivenName( $first_name );
$name->setFamilyName( $last_name );
$person->setNames( [ $name ] );

// Update with field mask
$updated = $service->people->updateContact(
    $google_contact_id,
    $person,
    [
        'updatePersonFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations',
        'personFields' => 'names,emailAddresses,phoneNumbers,addresses,organizations,metadata'
    ]
);

// Update stored etag
update_post_meta( $post_id, '_google_etag', $updated->getEtag() );
```

### Uploading a Contact Photo
```php
// Source: Google People API updateContactPhoto documentation
$service = new \Google\Service\PeopleService( $client );

// Get featured image
$thumbnail_id = get_post_thumbnail_id( $post_id );
if ( ! $thumbnail_id ) {
    return; // No photo to upload
}

// Get image file path
$image_path = get_attached_file( $thumbnail_id );
if ( ! $image_path || ! file_exists( $image_path ) ) {
    return;
}

// Read and encode as base64
$photo_bytes = base64_encode( file_get_contents( $image_path ) );

// Create request
$request = new \Google\Service\PeopleService\UpdateContactPhotoRequest();
$request->setPhotoBytes( $photo_bytes );
$request->setPersonFields( 'photos,metadata' );

// Upload photo
$response = $service->people->updateContactPhoto( $google_contact_id, $request );
```

### Save Post Hook Pattern
```php
// Source: existing pattern in class-auto-title.php, class-carddav-backend.php
add_action( 'save_post_person', [ $this, 'on_person_saved' ], 10, 3 );

public function on_person_saved( $post_id, $post, $update ) {
    // Skip autosaves and revisions
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Skip non-published posts
    if ( $post->post_status !== 'publish' ) {
        return;
    }

    // Check if user has Google Contacts export enabled
    $user_id = $post->post_author;
    if ( ! $this->user_has_export_enabled( $user_id ) ) {
        return;
    }

    // Queue for async export
    $this->queue_export( $post_id );
}
```

## Field Mapping: Stadion to Google

Based on the import class and ACF fields, here is the reverse mapping:

| Stadion Field | Google Field | Notes |
|--------------|--------------|-------|
| `first_name` | `Name.givenName` | Singleton - one value only |
| `last_name` | `Name.familyName` | Singleton - one value only |
| `contact_info` (email) | `EmailAddress[]` | Multiple allowed |
| `contact_info` (phone/mobile) | `PhoneNumber[]` | Multiple allowed, type from contact_type |
| `contact_info` (website, linkedin, etc) | `Url[]` | Multiple allowed |
| `addresses` | `Address[]` | Multiple allowed |
| `work_history` | `Organization[]` | Multiple allowed, includes title |
| Featured image | `updateContactPhoto` | Separate API call |
| Birthday (from important_date) | `Birthday` | Singleton, optional |

### Contact Type Mapping
| Stadion `contact_type` | Google Type |
|-----------------------|-------------|
| `email` | `EmailAddress` |
| `phone` | `PhoneNumber` (type: "home") |
| `mobile` | `PhoneNumber` (type: "mobile") |
| `website` | `Url` |
| `linkedin` | `Url` (can set type) |
| `twitter` | `Url` |
| `facebook` | `Url` |
| `instagram` | `Url` |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Sync on save | Async queue + cron | Current pattern | Non-blocking saves |
| Google Contacts API v2 | People API | 2019 | Different endpoint structure |
| Readonly scope | Readwrite scope for export | Phase 79 | Already supports both modes |

**Deprecated/outdated:**
- Contacts API v2: Replaced by People API, use `Google\Service\PeopleService`
- gdata library: Use google-api-php-client instead

## Open Questions

Things that couldn't be fully resolved:

1. **Photo size limits**
   - What we know: API docs don't specify maximum size
   - What's unclear: Exact byte limits, whether Google auto-resizes
   - Recommendation: Resize to reasonable max (400px) before upload, consistent with import

2. **Rate limiting thresholds**
   - What we know: Google recommends sequential mutations
   - What's unclear: Exact rate limits for People API
   - Recommendation: Process one contact at a time, add delays between bulk exports

3. **Birthday export**
   - What we know: Birthdays stored as separate `important_date` posts
   - What's unclear: Whether to export birthdays or just contact fields
   - Recommendation: Start with core contact fields, add birthday in future if requested

## Sources

### Primary (HIGH confidence)
- Google People API official documentation (fetched 2026-01-17):
  - [createContact](https://developers.google.com/people/api/rest/v1/people/createContact)
  - [updateContact](https://developers.google.com/people/api/rest/v1/people/updateContact)
  - [updateContactPhoto](https://developers.google.com/people/api/rest/v1/people/updateContactPhoto)
- Existing codebase files:
  - `/Users/joostdevalk/Code/stadion/vendor/google/apiclient-services/src/PeopleService/Resource/People.php`
  - `/Users/joostdevalk/Code/stadion/includes/class-google-contacts-api-import.php`
  - `/Users/joostdevalk/Code/stadion/includes/class-google-oauth.php`
  - `/Users/joostdevalk/Code/stadion/includes/class-auto-title.php` (async cron pattern)

### Secondary (MEDIUM confidence)
- google-api-php-client GitHub issues for PHP examples
- [Google People API Contacts guide](https://developers.google.com/people/v1/contacts)

### Tertiary (LOW confidence)
- WebSearch results for rate limiting (no authoritative source found)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already installed and working in codebase
- Architecture: HIGH - Patterns directly from existing codebase
- Field mapping: HIGH - Derived from existing import class
- API usage: HIGH - Verified against official Google documentation
- Pitfalls: MEDIUM - Based on documentation, not production experience

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (Google APIs are stable)
