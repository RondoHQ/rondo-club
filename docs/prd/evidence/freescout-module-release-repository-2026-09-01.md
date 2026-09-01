# FreeScout module repository and release evidence

**Date:** 2026-09-01<br>
**Method:** read-only GitHub repository/API inspection and FreeScout upstream source review

## Repository discovery

The RondoHQ organization contained the public Rondo Club, developer, sync, player, website and
organization-profile repositories. `RondoHQ/freescout-rondo-integration` did not exist, so no
existing source, releases, tags or settings are being renamed or overwritten by this decision.

The reserved repository is:

```text
https://github.com/RondoHQ/freescout-rondo-integration
```

It will be public because installed FreeScout instances must fetch stable update metadata and
artifacts without a GitHub credential. Its source license is `AGPL-3.0-only`, matching FreeScout's
published license. MIT notices remain attached to reused or substantially derived code from
`fulldecent/freescout-sidebar-webhook`.

## Upstream facts used

- FreeScout reports `AGPL-3.0` and publishes the GNU Affero General Public License version 3:
  [FreeScout repository](https://github.com/freescout-help-desk/freescout).
- Sidebar Webhook reports the MIT license:
  [Sidebar Webhook repository](https://github.com/fulldecent/freescout-sidebar-webhook).
- FreeScout's updater downloads a third-party module ZIP, extracts it into the Modules directory
  and runs the module-install command without a checksum check first:
  [FreeScout Module updater](https://github.com/freescout-help-desk/freescout/blob/dist/app/Module.php).
- GitHub immutable releases lock the release tag and assets and create a release attestation after
  publication:
  [GitHub immutable releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases).

## Decision boundary

This evidence reserves the name and release contract only. It does not create the repository,
enable organization settings, publish a tag or install an artifact.
