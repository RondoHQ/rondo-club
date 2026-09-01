# FreeScout production mailbox mapping evidence

**Date:** 2026-09-01<br>
**Method:** read-only FreeScout REST API request from the configured production Rondo Sync host<br>
**Credentials:** used server-side and not printed or stored in this evidence

## Result

`GET /api/mailboxes` returned HTTP `200` with 17 mailboxes. Filtering the returned names for
`Ledenadministratie` produced exactly one result:

| ID | Name | Address |
|---:|---|---|
| `18` | Ledenadministratie | `ledenadministratie@svawc.nl` |

The list representation exposes `id`, `name`, `email`, `createdAt` and `updatedAt`. It does not
expose FreeScout's local `Mailbox::isActive()` result. A read-only `GET /api/mailboxes/18` is not
supported by the installed API and returned HTTP `405`.

## Configuration decision

- Stable Rondo mailbox key: `ledenadministratie`.
- Environment-specific production FreeScout ID: `18`.
- Name and address are the verification snapshot, not lookup keys.
- Rondo Integration must load mailbox `18` locally and require `Mailbox::isActive()` before managed
  provisioning is enabled.
- Missing, inactive or drifted configuration must never fall back to searching by name or address.
