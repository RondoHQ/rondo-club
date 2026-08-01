# ACF production preflight

Run this preflight before merging the 34.0.0 ACF-removal pull requests. It records the production-only evidence needed to map every dynamic field and every known persisted field identifier before the public and storage contracts change.

The commands are read-only. They issue `SELECT` queries, omit all field and setting values from their JSON, and do not migrate definitions or preferences.

## Capture the evidence

Run from the production WordPress root after this preflight release has deployed. Store the output in a private directory outside the public web root.

```bash
mkdir -p ../private/acf-preflight
chmod 700 ../private/acf-preflight

wp rondo acf-preflight export-dynamic \
  > ../private/acf-preflight/dynamic-fields.json
wp rondo acf-preflight audit-persisted \
  > ../private/acf-preflight/persisted-field-references.json

php -r 'foreach (array_slice($argv, 1) as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo "$file: valid JSON\n"; }' \
  ../private/acf-preflight/dynamic-fields.json \
  ../private/acf-preflight/persisted-field-references.json

sha256sum ../private/acf-preflight/*.json
```

Download the JSON and checksum output to the protected rollout record. Do not commit the raw production exports to Git.

## Review the dynamic-field export

For every `person`, `team`, and `commissie` definition, confirm:

- the immutable key, storage name, canonical name, type, state, order, and configuration match the intended 34.0.0 registry entry;
- no dynamic canonical name collides with a static field;
- every definition with `stored_posts > 0` is retained, even when `populated_posts` is zero because the only stored value is `0`;
- inactive definitions with stored data remain recoverable;
- every removal decision is explicitly recorded.

## Review persisted references

For every reported user-meta or option hit, record its owning schema and migration strategy. Unknown option hits block the cutover until their owner is identified. Browser local storage and external bookmarks cannot be counted server-side; the report records the compatibility strategy for them instead.

The WordPress preflight is complete when both JSON documents parse, their checksums are recorded, every dynamic definition has a disposition, and every reported persisted identifier has a migration or compatibility decision. A fresh database backup is still required immediately before the later Club/Sync cutover.
