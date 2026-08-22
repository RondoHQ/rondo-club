# PRD: Rondo narrowcasting and Rondo Player

> Replace Sportlink Club.TV with a subscription-free narrowcasting system managed from Rondo and
> displayed by a Raspberry Pi 5 attached to each television.

**Status:** Pilot Milestones 1–3 and signed player updates implemented
**Components:** Rondo Club, Rondo Player, Sportlink Club.Data  
**Date:** 2026-08-15  
**Owner:** Club administrators and sponsor management  
**First hardware:** Raspberry Pi 5, 4 GB

---

## 1. Executive summary

Rondo will become the club's narrowcasting content-management system. Each independently controlled
television gets one **Rondo Player**: a Raspberry Pi 5 that connects over Wi-Fi, opens a dedicated
Rondo display in a full-screen browser, caches content locally, reports its health, and wakes or
sleeps the television through HDMI-CEC.

The first release must reach functional parity with the Club.TV use cases that matter to AWC:

- today's matches;
- pitches and dressing-room assignments;
- cancellations and schedule changes;
- recent results and upcoming matches;
- sponsor rotation;
- club announcements;
- scheduled television wake and standby;
- different content on different screens.

There is no external signage subscription. Rondo owns the player software and management surface.
The existing Sportlink Club.Data client ID is reused server-side for match data. The supplied
Sportlink WordPress plugins prove that the necessary Club.Data endpoints and fields are available.

The delivery strategy is deliberately incremental: make the first Pi reliably wake one TV and show
one Rondo screen, then add live matchday data, content management, and finally fleet hardening and
the Club.TV cutover.

---

## 2. Problem

Sportlink Club.TV is relatively expensive and does not offer enough control over presentation,
sponsor rotation, screen-specific content, or future Rondo integrations. Cancelling the service
also means returning the Sportlink set-top boxes.

The replacement must therefore cover two separate problems:

1. **Content:** Rondo needs to build, schedule and serve useful club and sponsor screens.
2. **Playback:** every TV needs a dependable appliance that starts unattended, survives network and
   power interruptions, and controls the TV without a volunteer using the remote.

A browser running on a smart TV is not sufficient. Smart-TV browsers differ by manufacturer, are
difficult to monitor, and cannot be trusted to restart the Rondo display after an update or crash.

---

## 3. Goals

### Product goals

- Replace the current Club.TV screens before the Sportlink subscription ends.
- Manage every display, playlist, sponsor item and announcement from Rondo.
- Use the existing Sportlink Club.Data service for live match information.
- Wake TVs before opening hours and put them in standby afterwards.
- Require no recurring player or narrowcasting subscription.
- Make installation simple enough that a club administrator can add a replacement player without
  Linux knowledge.
- Continue showing useful content during a temporary internet or Sportlink outage.
- Know from Rondo whether a player is healthy and what it last displayed.

### Success measures

- A content change reaches an online player within five minutes.
- Matchday information is no more than ten minutes behind the last successful Sportlink refresh.
- A player resumes playback within two minutes after power or network recovery.
- A player can show its last known playlist for at least 24 hours without internet.
- No screen remains blank for more than ten seconds because of a browser crash.
- At least 99% of planned display time during the pilot is healthy playback time.
- HDMI-CEC wake, input selection and standby work on every television accepted into the rollout.
- A club administrator can pair a prepared player in less than ten minutes.

---

## 4. Non-goals for the first release

- Replacing Sportlink Club.Data itself.
- Importing member profiles, birthdays or team rosters from the FCM for Sportlink plugin.
- Interactive touchscreens.
- Video walls or synchronised playback across several screens.
- Live television capture or picture-in-picture.
- Guaranteed audio playback; all initial video content is muted.
- A sponsor self-service portal.
- Proof-of-play suitable for contractual advertising billing.
- Arbitrary remote shell access from Rondo.
- Portrait displays or unusual resolutions; v1 targets 16:9 landscape screens.
- Weather, social-media or news feeds unless separately approved later.

---

## 5. Users and permissions

### Club administrator

Can configure Sportlink, create and pair players, set opening hours, assign playlists, issue safe
player commands, and see fleet health.

### Narrowcasting editor

Can create announcements and media items, manage playlists, preview screens, and schedule content.
Cannot change player credentials or Sportlink settings.

### Sponsor manager

Can create and schedule sponsor content and relate it to an existing Rondo sponsor record. Cannot
manage players or ordinary club announcements unless they also hold the editor capability.

### Player

Authenticates with a device-specific secret and receives only public display content plus its own
configuration and commands. It never receives member records, sponsor contact details, WordPress
cookies, the Sportlink client ID, or another player's data.

Rondo should introduce a dedicated `narrowcasting` capability and keep `sponsorbeheer` limited to
sponsor content. Administrators receive both by default.

---

## 6. Hardware standard

Each independently controlled TV gets one player.

### Required player kit

- Raspberry Pi 5 with 4 GB RAM;
- official Raspberry Pi 27 W USB-C power supply;
- actively cooled case suitable for continuous operation;
- 32 GB or 64 GB high-endurance microSD card;
- short, certified micro-HDMI-to-HDMI cable;
- permanent mains power independent of the TV's USB ports;
- bracket or reusable mounting solution behind the television;
- Wi-Fi access, with Ethernet retained as an optional fallback.

The Pi stays powered continuously. Outside display hours the television is in standby while the
player keeps its schedule, checks for updates, and remains able to wake the TV.

### Television requirements

- HDMI input;
- HDMI-CEC support enabled in the TV settings;
- ability to wake from standby through CEC;
- ability to select or remain on the player's HDMI input;
- auto-sleep, screensaver and eco timers configured not to interrupt planned playback;
- mains power left on.

HDMI-CEC has manufacturer-specific names such as Anynet+, Simplink, Bravia Sync, EasyLink and
Viera Link. Support must be tested on the actual TV model; a specification-sheet claim alone is
not sufficient.

### Pilot rule

Buy and configure one complete player first. Test that player on every television model in scope
before ordering the remaining kits. Record the working CEC settings and any model-specific delays
or retries.

---

## 7. Player experience

### First boot

1. The prepared SD card boots directly into Rondo Player.
2. If Wi-Fi is not configured, the player shows clear local setup instructions. The pilot may use
   Wi-Fi preconfigured during image preparation; a phone-friendly onboarding flow is required
   before a general release.
3. After reaching Rondo, the TV shows a short-lived activation code.
4. An administrator opens **Narrowcasting → Players**, enters the code, names the screen, and
   assigns its initial playlist and opening hours.
5. The player receives a device secret, stores it locally with root-only permissions, and starts
   playback.

No WordPress username or password is entered on the player.

### Normal boot

1. The operating system starts without an interactive login.
2. The local player service loads its saved identity and last-known schedule.
3. It wakes the TV if the current time is within display hours.
4. Chromium starts in kiosk mode without browser chrome, cursor, dialogs or notifications.
5. The display app loads the current playlist, falling back to cached content if Rondo is
   unavailable.

### Local recovery

- The browser is restarted automatically if it exits or stops responding.
- The player service restarts after a failure.
- A failed network connection does not blank the screen.
- CEC wake commands are retried with configurable delays.
- On restoration of power, the player reconciles the current time rather than waiting for the next
  scheduled edge. A TV that should currently be on is therefore woken immediately.
- An intentional standby period shows no content and does not repeatedly wake the TV.

### Safe remote commands

Rondo can queue only predefined commands:

- reload display;
- restart browser;
- reboot player;
- shut down player;
- wake TV;
- put TV in standby;
- run CEC detection/test;
- refresh configuration;
- update the Rondo Player package to an approved signed version.

Arbitrary shell commands are explicitly prohibited.

---

## 8. Content model

All persistent data uses WordPress native data models. No custom database tables are introduced.

### `rondo_display` custom post type

Represents one physical player/screen.

Key metadata:

- display name and location;
- enabled/disabled status;
- orientation and target resolution;
- timezone;
- weekly opening hours and date-specific exceptions;
- assigned playlist;
- CEC enabled, logical address, wake retries and model notes;
- device public identifier and hashed device secret;
- player software version and content version;
- last-seen time, last successful content sync and last error summary;
- last reported TV power state, Wi-Fi signal, temperature, storage and uptime.

Frequent heartbeat presence can use a transient, while durable diagnostic summaries are written to
post meta at a lower frequency to avoid unnecessary database churn.

### `rondo_signage_item` custom post type

Represents one playable scene.

Initial content types:

- sponsor card;
- uploaded image;
- muted video;
- club announcement;
- today's matches and dressing rooms;
- upcoming matches;
- recent results;
- cancellations;
- fallback/welcome screen.

Common metadata:

- content type and enabled status;
- display duration;
- valid-from and valid-until timestamps;
- priority;
- optional sponsor-person relationship;
- image/video attachment IDs;
- title, body and call-to-action text where applicable;
- background, text and accent colours from an approved palette;
- rules for showing only when the dynamic module contains data.

Sponsor records remain `person` posts with `is_sponsor=true`. Signage assets are separate items so
one sponsor can have several campaigns without mixing advertising files into contact data.

### `rondo_signage_list` custom post type

Represents an ordered or weighted collection of signage items.

Key metadata:

- ordered items;
- per-item duration override;
- optional weight/frequency;
- default transition;
- valid date window;
- days of week and time window;
- fallback playlist or item.

The playlist relationship is stored as a native repeater through Rondo's field registry: one count
row plus numbered child post-meta rows.

### Media

- Original files live in the WordPress media library.
- The server produces display-appropriate derivatives instead of sending oversized originals.
- Images support JPEG, PNG and WebP.
- Initial video support is H.264 MP4 without required audio.
- Upload validation rejects unsupported formats and files above configured limits.
- Players cache media by immutable versioned URL and discard unreferenced media after a grace
  period.

---

## 9. Playback rules

The first release uses full-screen scenes rather than a multi-zone canvas. This creates a clean and
readable result at clubhouse viewing distance and keeps the player reliable.

### Rotation

- A playlist repeats continuously during display hours.
- Each scene has a configurable duration, with sensible defaults per type.
- Weighted items may appear more than once per cycle, but the player avoids showing the same item
  twice consecutively.
- Invalid, disabled or empty dynamic items are skipped.
- A playlist always resolves to at least one fallback scene.
- Content switches with a short crossfade; no white or desktop flash is visible.

### Overrides

- A high-priority announcement can temporarily override normal rotation on selected displays.
- Overrides have explicit start and end times.
- Expired overrides are removed automatically.
- An emergency override remains a future extension unless separately approved; v1 does not claim
  to be a safety-notification system.

### Preview

Editors can preview a playlist in Rondo at a 16:9 aspect ratio, select a simulated date/time, and
see why a scheduled or dynamic item is included or skipped.

---

## 10. Sportlink Club.Data integration

### Evidence from the supplied plugins

The attached Sportlink WordPress 4.0.1 plugin uses the base URL
`https://data.sportlink.com/` with a Sportlink client ID. Its program view requests fields that
directly cover the narrowcasting use case:

- `wedstrijddatum`, `datum`, `aanvangstijd`;
- `thuisteam`, `uitteam`, team IDs and club relationship codes;
- `thuisteamlogo`, `uitteamlogo`;
- `wedstrijdcode`, `wedstrijdnummer`, `wedstrijd`;
- `status`;
- `accommodatie`, `veld`, `locatie`, `plaats`;
- `kleedkamerthuisteam`, `kleedkameruitteam`, `kleedkamerscheidsrechter`;
- `scheidsrechters` and `scheidsrechter`.

The plugin also shows that `programma` responses contain a `meer` value pointing to
`wedstrijd-informatie`, which supplies grouped accommodation, dressing-room, official, team and
match details.

### Required endpoints

| Endpoint | Initial use | Refresh target |
|---|---|---:|
| `programma` | Today's and upcoming fixtures, pitches and dressing rooms | 5 minutes on matchdays |
| `wedstrijd-informatie` | Expanded details when the program response is incomplete | With its parent match |
| `afgelastingen` | Dedicated cancellation view and status override | 5 minutes on matchdays |
| `uitslagen` | Recent results | 15 minutes |
| `teams` | Team reference data | Daily |
| `poulestand` | Optional later standings module | Not in pilot |
| `clublogo` | Club logos when not included directly | Cache long-term |

Exact accepted parameters and response shapes must be captured as contract fixtures from the
club's real client ID during implementation. Fixtures must be redacted where appropriate and must
cover a normal match, a cancellation, missing dressing-room data and an away match.

### Server-side adapter

Rondo calls Club.Data from WordPress through the WordPress HTTP API. Players never call Sportlink
directly. This provides:

- one place for the client ID;
- response validation and normalization;
- rate control;
- last-known-good data;
- consistent formatting and timezone handling;
- a stable Rondo display API if Sportlink changes fields later.

The Sportlink client ID is stored through the Options API and exposed only to administrators. It is
never included in frontend configuration, page HTML or player responses.

### Normalized match shape

The display frontend receives a small Rondo-owned representation, for example:

- stable match ID and source update time;
- start timestamp with explicit timezone;
- home/away team names and cached logo URLs;
- home/away indicator for the club;
- pitch;
- home, away and referee dressing rooms;
- status and cancellation flag;
- officials only when intentionally enabled;
- source freshness and stale indicator.

No member records or contact fields are included.

### Caching and failures

- Rondo stores each normalized feed with `fetched_at`, `fresh_until` and the last successful
  payload using the Options API or transients as appropriate.
- A failed refresh never replaces good cached data with an empty response.
- Players are told when data is stale.
- Matchday content may keep showing stale assignments with a visible “last updated” time for up to
  24 hours; after that it falls back to a neutral service-unavailable scene.
- Administrators can see the last success, last error and next planned refresh.
- A manual **Refresh Sportlink now** action is available and rate-limited.

### Relationship to other plugins

The supplied Sportlink WordPress plugin is a reference, not a runtime dependency. Its shortcode,
template and browser-fetching architecture is not installed into Rondo.

The independent [FCM for Sportlink plugin](https://wordpress.org/plugins/fcm-for-sportlink/)
imports public team rosters, profiles, photos, fixtures, results and birthdays hourly into Football
Club Manager. That import model is outside this feature: Rondo already has its own people/team
model and only needs a small cached matchday adapter for narrowcasting.

---

## 11. Rondo display API

The display shell is publicly loadable but contains no content by itself and is marked `noindex`.
All useful data requires a paired device credential.

Proposed endpoints under `/rondo/v1/narrowcasting/`:

| Method and route | Purpose |
|---|---|
| `POST /devices/claim` | Player exchanges an approved activation code for its device secret |
| `GET /devices/me/config` | Opening hours, CEC settings, playlist and content version |
| `POST /devices/me/heartbeat` | Health, playback state and software versions |
| `POST /devices/me/commands/ack` | Acknowledge the result of a predefined command |
| `GET /devices/me/commands` | Poll for safe queued commands |
| `GET /devices/me/playlist` | Resolved, sanitized playlist manifest |
| `GET /feeds/matchday` | Normalized matchday feed available only to paired players/editor preview |

Administrative CRUD routes require normal WordPress session authentication, nonce validation and
the appropriate capability.

### Device authentication

- Activation codes are random, single-use and short-lived.
- The permanent device secret has at least 256 bits of entropy.
- Rondo stores only a keyed hash of the secret.
- Requests use `Authorization: Bearer`, never a query parameter.
- A compromised player can be revoked without affecting other screens.
- Pairing and revocation events are auditable.
- Device responses include only that player's effective configuration.
- Rate limits apply to activation, heartbeat and command polling.

The browser display stores its credential locally and sends it in the authorization header. If a
bootstrap handoff through the URL is required, it uses the URL fragment, stores the credential, and
immediately removes the fragment so the secret is not sent in HTTP requests or access logs.

---

## 12. Rondo administration interface

Add a **Narrowcasting** section visible only to authorized users.

### Overview

- Cards for total, online, offline and attention-needed players.
- Last Sportlink refresh and freshness warning.
- Currently active override.
- Upcoming scheduled content expirations.
- Direct preview of the default playlist.

### Players

- Add/claim player.
- Name, location, active playlist and opening-hours summary.
- Online state and last seen.
- Player version, current scene and last content sync.
- CEC and TV power state.
- Wi-Fi strength, temperature and storage warnings.
- Safe command buttons with confirmation where appropriate.
- Revoke and re-pair flow.
- CEC test wizard: detect, wake, select input, standby and restore.

### Content

- Filter by type, status, sponsor and validity.
- Create sponsor card from logo, company name and optional text.
- Upload finished 16:9 artwork or muted video.
- Create a club announcement from a constrained template.
- Preview at 1920×1080 proportions.
- Duplicate an item for a new campaign.
- Warn about low-resolution images, unreadable text and expired dates.

### Playlists

- Add and reorder items.
- Configure duration and weight.
- Add dynamic Sportlink modules.
- Assign one playlist to several players.
- Preview a complete cycle and its estimated length.
- Show excluded or empty items with reasons.

### Settings

- Sportlink client ID and connection test.
- Club timezone and default display hours.
- Default/fallback playlist.
- Default scene durations and transitions.
- Media size limits.
- Player update channel.

---

## 13. Rondo Player software

The player software should live in a separate `rondo-player` repository because it has its own OS
image, release artifacts and hardware test cycle. Rondo Club remains the source of truth and owns
the REST contract.

### Base system

- Raspberry Pi OS 64-bit;
- Chromium in kiosk mode;
- lightweight local Rondo Player service managed by `systemd`;
- `cec-utils`/libCEC for TV control;
- network time synchronization;
- watchdog for the agent and browser;
- unattended security updates with controlled reboot windows;
- no exposed inbound administration ports by default;
- default account credentials disabled or randomized.

### Responsibilities of the local service

- pairing and secure credential storage;
- configuration, command and schedule polling;
- CEC wake, input and standby actions;
- Chromium process supervision;
- local telemetry and heartbeat submission;
- offline schedule retention;
- controlled player-package updates;
- cleanup of old cached files;
- a local diagnostics screen when playback cannot start.

### Responsibilities of the browser app

- playlist resolution and scene rotation;
- responsive 16:9 rendering;
- matchday, sponsor, announcement and media scenes;
- media preloading;
- service-worker/Cache Storage offline playback;
- frontend error reporting;
- clean transitions without browser chrome.

### Image distribution

- Builds are versioned and reproducible.
- A checksum accompanies every downloadable image.
- Player-package updates are signed and limited to Rondo release artifacts.
- The installation guide describes flashing, Wi-Fi setup, pairing and recovery.
- A recovery image can restore a failed card without access to production credentials.

The pilot may start with a manually prepared image, but fleet rollout does not start until the
build and recovery process is documented and repeatable.

---

## 14. Offline and degraded operation

The player must always prefer an older valid screen over a blank or browser error page.

### Rondo unavailable

- Continue the last successfully downloaded playlist.
- Retain current opening hours and CEC behaviour.
- Queue a small bounded amount of diagnostic information locally.
- Retry with exponential backoff and jitter.

### Sportlink unavailable

- Continue last-known-good match data and show its update time.
- Do not replace assignments with empty values.
- After the configured maximum stale period, skip the matchday scene or show a neutral fallback.

### Media download failure

- Keep the previous manifest active until all mandatory assets for the new version are ready.
- Skip a broken optional item and report it.
- Never switch to a partially downloaded playlist.

### Wi-Fi not configured or lost permanently

- Show a local diagnostics screen with the player name, connection status and non-secret recovery
  instructions.
- Do not reveal the Wi-Fi password or device credential.

---

## 15. Observability and operations

### Player heartbeat

During display hours, players report at least every minute; outside display hours they may report
less frequently. The administration UI marks a player:

- **Online:** recent heartbeat and normal playback;
- **Degraded:** online but stale content, weak Wi-Fi, high temperature, low storage or repeated CEC
  failure;
- **Offline:** heartbeat outside the allowed threshold;
- **Sleeping:** expected standby period, not an incident.

### Alerts

The pilot needs dashboard warnings. Email or other active alerts can follow once thresholds prove
useful. Avoid generating notifications for planned sleeping periods.

### Logs

- Retain concise structured events, not unbounded raw browser logs.
- Redact device secrets, authorization headers and Wi-Fi credentials.
- Include player ID, software version, content version and timestamps.
- Keep enough history to diagnose the last failed boot, sync or CEC action.

---

## 16. Security and privacy

- The display API is a separate least-privilege authentication boundary.
- No WordPress admin session is stored on a player.
- No personal member data is part of a display response.
- Sponsor contact details are excluded; only approved public campaign content is exposed.
- Only the minimum necessary official/referee fields are included and these can be disabled.
- Public display pages use `noindex`, a restrictive Content Security Policy and no third-party
  analytics.
- Player secrets can be individually rotated and revoked.
- Uploaded content is validated by MIME type and rendered without executing user-provided HTML or
  JavaScript.
- Remote commands are enumerated, authenticated, expire, and cannot carry arbitrary arguments.
- The local player does not expose SSH or a management interface to the public internet.

---

## 17. Accessibility and visual requirements

Although screens are not interactive, they must remain readable in a bright clubhouse and from a
distance.

- Minimum text sizes are defined per template; editors cannot shrink text below them.
- Text and background combinations meet WCAG AA contrast where applicable.
- Critical pitch and dressing-room values receive the strongest visual hierarchy.
- Do not encode match status using colour alone.
- Announcements have maximum text lengths and predictable line counts.
- Respect safe margins for TVs with overscan.
- Show dates and times in Dutch and in the configured club timezone.
- Avoid rapid animation, flashing content and transitions longer than one second.

---

## 18. Milestones

### Milestone 0 — Inventory and contracts

- Inventory every TV, location, model, HDMI port, power point and Wi-Fi signal.
- Confirm HDMI-CEC settings and terminology per model.
- Record the current Club.TV playlists and collect original sponsor assets.
- Capture redacted Club.Data fixtures and finalize the normalized match contract.
- Create the separate Rondo Player repository and hardware test checklist.

**Exit:** hardware inventory is complete and the Sportlink responses required for the pilot are
captured.

### Milestone 1 — One-screen technical pilot

- Prepare the first Pi image.
- Boot Chromium into a static Rondo display route.
- Implement pairing, heartbeat and basic player status.
- Implement fixed weekly display hours and CEC wake/input/standby.
- Add browser and service supervision.
- Test power loss, Wi-Fi loss and a browser crash.

**Exit:** one Pi runs unattended for seven consecutive days, wakes and sleeps the pilot TV
correctly, and recovers from the tested failures.

### Milestone 2 — Matchday parity

- Add Sportlink settings and server-side cached adapter.
- Build today's matches, dressing rooms, pitches, cancellations and results scenes.
- Add stale-data handling and last-updated indicators.
- Verify data against the existing website and Club.TV on a real matchday.

**Exit:** the pilot screen can replace Club.TV for operational matchday information.

### Milestone 3 — Sponsors and playlists

- Add signage items, media validation and sponsor relationships.
- Add playlists, duration/weight controls, schedules and preview.
- Add announcements, fallback content and temporary overrides.
- Add narrowcasting and sponsor permissions.

**Exit:** authorized users can reproduce the current sponsor rotation and manage it without code.

### Milestone 4 — Fleet readiness

- Reproducible image and signed player-package releases.
- General Wi-Fi onboarding and activation-code pairing.
- Complete player diagnostics, command handling and update flow.
- Verify offline playback and atomic content updates.
- Document installation, replacement-card recovery and CEC troubleshooting.

**Exit:** a second player can be installed from the documentation without developer intervention.

### Milestone 5 — Cutover

- Install all approved players.
- Run Rondo and Club.TV in parallel long enough to cover at least one representative matchday.
- Fix differences in fixtures, rooms, cancellations and sponsor rotation.
- Confirm Club.TV cancellation date and hardware return instructions.
- Preserve proof that all loaned Sportlink boxes and accessories were returned.

**Exit:** every production TV is on Rondo Player, monitoring is healthy, and Club.TV can be
cancelled without losing display service.

Planning-only milestones do not change the theme version or changelog. Each implementation
milestone follows the normal version, changelog, test, documentation, commit, push and deployment
rules.

---

## 19. Acceptance criteria

### Player and TV

- [ ] A powered Pi starts playback without a keyboard, mouse or manual login.
- [ ] The player connects over the clubhouse Wi-Fi and retains its configuration after reboot.
- [ ] The paired TV wakes, selects the correct HDMI input and enters standby on schedule.
- [ ] A missed wake boundary caused by power loss is reconciled after restart.
- [ ] Chromium and the local service recover automatically from forced termination.
- [ ] The TV never depends on power from its own USB port to run the player.

### Content

- [ ] Editors can create, preview, schedule and disable announcements and media.
- [ ] Sponsor managers can manage sponsor items without gaining player-administration access.
- [ ] Different displays can use different playlists and opening hours.
- [ ] A missing or expired scene never results in an empty playlist.
- [ ] Videos autoplay muted and unsupported media is rejected before scheduling.

### Sportlink

- [ ] Today's fixtures match the website for the same Club.Data client ID.
- [ ] Home and away teams, start times, status, pitch and available dressing rooms render correctly.
- [ ] Cancellations replace normal status promptly.
- [ ] A failed Sportlink response preserves the last-known-good payload.
- [ ] The client ID is absent from browser source, player payloads and logs.

### Administration and security

- [ ] Pairing codes expire and cannot be reused.
- [ ] Revoking one player immediately blocks that credential without affecting others.
- [ ] Display APIs return no member or sponsor contact data.
- [ ] Offline, degraded and intentionally sleeping devices are distinguishable.
- [ ] Safe remote commands are auditable and expire if not executed.

### Offline behaviour

- [ ] Disconnecting internet after a complete sync leaves at least 24 hours of usable playback.
- [ ] A playlist update becomes active only after its required assets are available.
- [ ] Reconnecting causes the player to refresh without manual intervention.

---

## 20. Test strategy

### Backend

- REST permissions for administrators, editors, sponsor managers, unpaired clients and paired
  devices.
- Activation expiry, one-time use, revocation and secret verification.
- Field-registry storage contracts for all three custom post types.
- Playlist resolution across schedules, weights, expired items, empty dynamic modules and fallback.
- Sportlink normalization using fixed fixtures, including malformed and partial responses.
- Cache behaviour proving that an upstream failure cannot overwrite good data.

### Frontend

- Scene rendering at 1920×1080 and 3840×2160 viewports.
- Long team names, missing logos, missing rooms and cancellation states.
- Sponsor and announcement text limits.
- Rotation, preloading, transition and fallback behaviour.
- Service-worker offline test with a fully downloaded playlist.

### Player

- Cold boot, abrupt power removal and restoration.
- Wi-Fi unavailable at boot, temporary loss and credential change.
- Browser crash, agent crash and corrupt local manifest.
- CEC wake, standby, input selection, retries and unsupported-TV fallback.
- Clock drift and timezone/DST transitions.
- High-temperature and low-storage reporting.
- Signed update success, invalid signature rejection and rollback/recovery.

### Field acceptance

Run the pilot for seven uninterrupted days and cover at least one real matchday before fleet
approval. Compare displayed assignments with Sportlink/website source data and record every manual
intervention.

---

## 21. Rollout and rollback

### Before cancellation

- Keep the Club.TV subscription active through the pilot and parallel matchday.
- Confirm which boxes, power supplies, remotes and cables must be returned.
- Export or collect original sponsor artwork; do not assume it remains downloadable after
  cancellation.
- Agree on a cutover and return date with enough buffer to correct defects.

### Rollback during rollout

- Keep the Sportlink player attached or immediately reinstallable until a TV passes acceptance.
- A failed Rondo player can be replaced with the known-good pilot image and re-paired.
- Rondo content changes can roll back to the previous playlist manifest.
- A broken player-package update rolls back to the previous signed version or recovery image.

After Club.TV cancellation, the operational fallback is the last-known-good Rondo Player image and
playlist, not the returned Sportlink hardware.

---

## 22. Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| A TV advertises CEC but does not reliably wake | Manual remote needed | Test every model before fleet purchase; configurable retries/delays; document unsupported fallback |
| Clubhouse Wi-Fi is weak behind a TV | Stale or offline display | Survey each location; prefer 5 GHz only where signal is strong; retain Ethernet option |
| Unexpected power loss corrupts an SD card | Player unavailable | High-endurance media, minimal writes, reproducible recovery image and spare prepared card |
| Sportlink changes or throttles Club.Data | Matchday data stale | Server-side adapter, contract fixtures, rate control and last-known-good payload |
| Browser or OS update changes kiosk behaviour | Blank/interrupted display | Controlled updates, watchdog, pilot ring and rollback |
| Sponsor assets exist only in Club.TV | Migration delay | Collect originals before cancellation |
| A device secret is copied from a player | Unauthorized feed access | Per-device revocation, hashed server storage, minimal non-personal payload |
| Scope expands into a full design canvas too early | Pilot delayed | Full-screen constrained templates for v1; multi-zone layout is a later decision |

---

## 23. Decisions made

- Use one Raspberry Pi 5 per independently controlled TV.
- Use Wi-Fi for normal network access and HDMI-CEC for TV power/input control.
- Keep the Pi permanently powered and put only the TV into standby.
- Build a subscription-free Rondo Player rather than adopt another signage SaaS.
- Keep the Rondo display as a browser application; use a small local service for device and CEC
  functions browsers cannot perform.
- Integrate directly with Club.Data on the Rondo server.
- Use native WordPress custom post types, post meta, options and transients; no custom tables.
- Start with full-screen scenes and constrained templates.
- Treat supplied WordPress plugins as API evidence, not production dependencies.
- Separate player/image releases into a companion `rondo-player` repository.

---

## 24. Open decisions before implementation

These do not block ordering the pilot hardware, but Milestone 0 must close them:

1. Exact number and locations of independently controlled TVs.
2. TV brand/model inventory and confirmed CEC behaviour.
3. Whether the first prepared image may contain the clubhouse Wi-Fi configuration or must launch
   with phone-based Wi-Fi onboarding immediately.
4. Club opening-hours defaults and date exceptions.
5. Which existing Club.TV sponsor assets are still current and who owns their originals.
6. Whether referee/official names should appear on public clubhouse screens.
7. Maximum accepted image/video sizes and whether sponsor videos are currently used.
8. Who receives `narrowcasting` permission in addition to administrators.

---

## 25. Sources

- Supplied Sportlink WordPress 4.0.1 plugin, especially `classes/config.class.php`,
  `classes/shortcodes.class.php`, `classes/teams.class.php` and `templates/wedstrijd_informatie.html`.
- Supplied `sportlink-wordpress-extras` plugin.
- [FCM for Sportlink](https://wordpress.org/plugins/fcm-for-sportlink/).
- [Raspberry Pi display configuration](https://www.raspberrypi.com/documentation/computers/configuration.html).
- [Using libCEC on Raspberry Pi](https://support.pulse-eight.com/support/solutions/articles/30000053003-cec-adapter-%E2%80%94-using-libcec-with-a-raspberry-pi).
- [Sportlink Club.TV FAQ](https://sportlink-help.freshdesk.com/nl/support/solutions/articles/9000096242-veelgestelde-vragen-club-tv).
