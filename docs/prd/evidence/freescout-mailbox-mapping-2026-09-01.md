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

The same response returned this complete planning inventory:

| ID | Name | Address |
|---:|---|---|
| `1` | Jeugdkamp | `jeugdkamp@svawc.nl` |
| `3` | Toernooien | `toernooien@svawc.nl` |
| `4` | Junioren | `junioren@svawc.nl` |
| `5` | Pupillen | `pupillen@svawc.nl` |
| `6` | O6 & 7 | `jo6@svawc.nl` |
| `7` | Sjors Sportief | `sjors@svawc.nl` |
| `8` | Wedstrijdzaken | `wedstrijdzaken@svawc.nl` |
| `9` | Contributie | `contributie@svawc.nl` |
| `10` | O8 | `jo8@svawc.nl` |
| `11` | Grote Clubactie | `clubactie@svawc.nl` |
| `12` | Info | `info@svawc.nl` |
| `16` | Demo | `demo@svawc.nl` |
| `17` | FairPlay | `fairplay@svawc.nl` |
| `18` | Ledenadministratie | `ledenadministratie@svawc.nl` |
| `19` | Communicatie | `communicatie@svawc.nl` |
| `20` | O9 | `jo9@svawc.nl` |
| `22` | O10 | `o10@svawc.nl` |

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
- The only pre-approved future candidates are FairPlay (`17`) for effective capability `fairplay`
  and Contributie (`9`) for effective capability `financieel`; neither is enabled by this record.
- All other mailbox access remains manually administered until an exact Rondo capability and
  approved sidebar policy exist.
