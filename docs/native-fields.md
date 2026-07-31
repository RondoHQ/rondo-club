# Native field system

Rondo owns its domain-field contract and stores values with WordPress metadata. No field plugin is
required at runtime, in development, or in CI.

## REST contract

Persisted domain values are exposed under the `fields` property on standard WordPress resources.
Names are canonical snake_case identifiers from `includes/config/field-registry.php`. Writes are
partial: omitted fields are unchanged, `null` clears a nullable scalar, and `[]` clears a repeater.
The retired legacy request attribute is rejected with HTTP 400 and is stripped from responses.

Dates use `YYYY-MM-DD`, datetimes use RFC 3339 with an explicit offset, and times use `HH:mm`.
Relationship writes accept only IDs and labels declared writable in the registry; enriched names,
thumbnails, and taxonomy labels are read-only.

## Registry and PHP API

`includes/config/field-registry.php` is the static source of truth. Each definition records its
canonical name, compatible storage name, immutable key, type, choices, defaults, limits,
multiplicity, date policy, and repeater children. `Rondo\Fields\Registry` resolves canonical names,
storage names, and immutable keys and rejects collisions or ambiguous lookups.

Production PHP reads and writes through `Rondo\Fields\Fields`:

```php
$email = Fields::get_for_post( $person_id, 'email_1' );
Fields::update_for_post( $person_id, 'email_1', 'member@example.org' );
Fields::update_many_for_post( $person_id, $changes );
Fields::update_for_term( 'relationship_type', $term_id, 'inverse_relationship_type', $inverse_id );
```

Use `update_many_for_post()` when several fields form one logical change. It validates the entire
payload before writing, supplies old and new values to validators, fires one update event per
changed field, and fires one logical saved-post event after storage succeeds.

## Storage layout

Scalars use their existing post- or term-meta key. Reference rows named `_<storage-key>` retain the
immutable field key, keeping current databases and rollback releases compatible. Repeaters retain
the numbered layout:

```text
addresses                  -> 2
addresses_0_city           -> Amsterdam
_addresses_0_city          -> field_address_city
addresses_1_city           -> Utrecht
```

Repeater writes replace the logical value, delete rows beyond the new count, and remove the parent
count/reference rows when the new value is empty. Domain side effects run once for the complete
logical update, not once per numbered child row.

## Dynamic custom fields

Active definitions are site data in the schema-versioned `rondo_dynamic_field_definitions` option.
Each definition has an immutable ID, storage key, canonical name, and type. Labels, presentation,
order, and validation settings remain editable. Deactivation is soft and never deletes field
values. The CLI can back up definitions and dry-run or apply an import. On first use, installations
with old definition posts receive a one-time native import without loading the old plugin.

## Adding or changing a field

1. Add or edit the context definition in `includes/config/field-registry.php`. Never reuse a
   canonical name, storage name, or immutable key in the same context.
2. Preserve an existing storage name when changing only the public API spelling.
3. Add formatter and storage tests for null/empty/default behavior, limits, and the exact return
   shape. Repeaters need grow, reorder, shrink, clear, and stale-row assertions.
4. Add REST contract tests for partial writes, unknown/read-only rejection, permissions, redaction,
   projections, and any hand-built endpoint that emits the field.
5. Update React, sync, fixtures, and saved-identifier migration maps together when a canonical name
   changes.
6. Run `composer test`, `composer lint`, `npm run lint`, and `npm run build` without field plugins.

## Deployment and rollback

Before deployment, export dynamic definitions and keep a database backup. Deploy the native release,
verify representative scalar, repeater, relationship, financial, volunteer, import/export, and sync
flows, then remove the old plugin from the installation. Rollback restores the prior theme release
and reactivates the plugin; compatible value and reference rows mean no reverse value migration is
required. Removing `_field` reference rows is optional and must remain a separate dry-run-first task.
