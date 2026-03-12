# Queued Milestones

## Q001: Remove CardDAV Backend Code

**Type:** cleanup  
**Priority:** normal  
**Reason:** CardDAV sync is unused — no active consumers. Dead code adds maintenance burden and the `sabre/dav` Composer dependency (~4.6) is only needed for this feature.

### Scope

**PHP files to delete:**
- `includes/carddav/class-auth-backend.php`
- `includes/carddav/class-carddav-backend.php`
- `includes/carddav/class-principal-backend.php`
- `includes/class-carddav-server.php`
- `includes/class-caldav-provider.php`

**PHP files to modify:**
- `functions.php` — remove CardDAV class loading, `rondo_is_carddav_request()`, class alias, rewrite rules, `CardDAVBackend::init_hooks()` call
- `includes/class-vcard-export.php` — review; may be shared with non-CardDAV vCard export (keep if so)
- `includes/class-rest-import-export.php` — remove any CardDAV-specific endpoints
- `includes/class-wp-cli.php` — remove CardDAV CLI commands if any

**Frontend files to modify:**
- `src/api/client.js` — remove CardDAV API methods
- `src/pages/Settings/Settings.jsx` — remove CardDAV connection UI

**Composer:**
- Remove `sabre/dav` dependency, run `composer update`

**Docs:**
- Update developer docs (`../developer/src/content/docs/integrations/`) to remove CardDAV references
