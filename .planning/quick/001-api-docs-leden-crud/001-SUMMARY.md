# Quick Task 001: API Documentation for Leden CRUD

**Completed:** 2026-01-25
**Output:** `docs/api-leden-crud.md`

## What Was Done

Created comprehensive API documentation for adding and updating "leden" (people) in Stadion.

## Documentation Includes

- **Authentication** - WordPress nonce-based auth with X-WP-Nonce header
- **Endpoints** - Full CRUD operations on `/wp/v2/people`
- **Field Reference** - All ACF fields with types and formats:
  - Basic info (first_name, last_name, gender, etc.)
  - Contact info (repeater with email, phone, social)
  - Addresses (repeater)
  - Team history (repeater with team links)
  - Relationships (repeater with person links)
  - Visibility settings
- **Code Examples** - JavaScript, PHP, and cURL
- **Error Handling** - Common error responses
- **Bulk Operations** - Using `/rondo/v1/people/bulk-update`
- **Photo Upload** - Using `/rondo/v1/people/{id}/photo`

## Files Created

| File | Description |
|------|-------------|
| `docs/api-leden-crud.md` | Complete API documentation |

## Notes

- Documentation is in Dutch terminology where relevant (leden, werkruimte)
- Includes practical code examples ready to copy/paste
- Covers all ACF field formats including repeaters
