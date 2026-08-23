# PRD: Room reservations and presentation access

> Let members reserve club rooms in Rondo and make the room's Club TV screen available only to
> the authorized reservation holders during their booking window.

**Status:** Plan — awaiting review  
**Components:** Rondo Club, Club TV, Rondo Player  
**Date:** 2026-08-23  
**Owner:** Accommodation management  
**Depends on:** [`narrowcasting-rondo-player.md`](narrowcasting-rondo-player.md)

---

## 1. Executive summary

Rondo gets a room-reservation module connected to the existing browser-presentation pilot. Logged-in
members can see room availability, but may reserve only on behalf of a commissie or year group in
which they currently hold a volunteer role. Year-group eligibility is derived from the team linked
to that volunteer role. Eligible members can manage their own future reservations and present to the
Club TV screen assigned to that room. The presentation entitlement begins shortly before the
reservation and ends when the booking ends. If nobody has the room afterwards, the current holder
can extend the reservation in small increments.

Accommodation managers get a dedicated day and week overview. They can see who reserved each room
and can create a reservation on behalf of an eligible holder, edit or cancel any reservation, or
create a management room block without a holder. Every management action is attributed and retained
in an audit trail. The affected reservation holder receives a notification.

The module uses WordPress-native custom post types and post meta. The existing `calendar_event` post
type is not reused: it is an external-calendar cache, while a room reservation is authoritative
Rondo data with its own permissions, conflict rules and presentation entitlement.

## 2. Confirmed product decisions

The following decisions come from product review and are fixed for the first implementation:

1. Reservations are created and managed in Rondo.
2. A physical room can be linked to a Rondo Club TV display.
3. The room's display is available to the reservation holder only during the allowed booking window.
4. Access may continue after the scheduled end only when the room has no subsequent reservation.
5. Accommodation managers need a complete overview showing who reserved which room.
6. Accommodation managers may view, add, edit and cancel reservations.
7. Management changes are logged and affected users are notified.
8. Every member reservation is made for exactly one commissie or year group.
9. The member creating the reservation must have a current volunteer role in that commissie or in
   a team belonging to that year group; a player role does not qualify.
10. A year group is derived from the current player roster of the team attached to the volunteer
    role, rather than introduced as a separately managed entity.
11. An accommodation manager may create a member reservation only on behalf of a holder who is
    eligible for the selected commissie or year group. Management room blocks without a holder are
    exempt from this membership rule.

## 3. Goals

- Give eligible volunteers one clear place to find and reserve available club rooms for their
  commissie or year group.
- Prevent overlapping reservations, including concurrent submissions.
- Give accommodation managers an operational day and week overview.
- Connect a reservation to the correct physical Club TV screen.
- Ensure a presentation code alone never grants access outside an authorized reservation.
- End or extend presentation access predictably at the reservation boundary.
- Preserve a durable history of creation, changes and cancellation.
- Avoid exposing reservation-holder details to ordinary users who only need availability.

## 4. Non-goals for the first release

- Synchronizing reservations bidirectionally with Google Calendar or Microsoft 365.
- Reservations by anonymous visitors or people without a Rondo account.
- Paid bookings, deposits, invoicing or key collection.
- Approval workflows; an allowed reservation is confirmed immediately.
- Recurring reservation series.
- Combining several rooms into one booking.
- Catering, inventory or equipment ordering.
- Automatically unlocking doors or controlling alarm systems.
- Guaranteed presentation across isolated guest networks; that requires TURN as a separate milestone.

An individual reservation can expose an iCalendar download without introducing calendar sync.

## 5. Users and permissions

### Member

Every approved, logged-in Rondo user may see room availability. A user may create a reservation only
when their linked person record contains a qualifying current volunteer role. An eligible user may:

- see which rooms are available or occupied;
- create a reservation for one of the commissies or year groups returned by their own eligibility
  check;
- see the full details of their own reservations;
- edit or cancel their own future reservations;
- name other approved Rondo users as authorized presenters;
- use the assigned screen during an active access window;
- extend their reservation when Rondo confirms that the room remains free.

Members may not see who holds somebody else's reservation, its private notes or contact details.
They see only an occupied time block.

A member with no qualifying current volunteer role sees the availability overview but no reservation
action. Rondo explains that room reservations are limited to active volunteers booking for their own
commissie or year group.

### Accommodation manager

Introduce a dedicated `accommodatiebeheer` capability and a built-in
`rondo_accommodatiebeheerder` role. The capability remains assignable through the existing role ×
capability matrix.

An accommodation manager may:

- see every reservation and reservation holder;
- add a reservation for an approved Rondo user who qualifies for the selected commissie or year
  group;
- edit any reservation, including its room and time;
- cancel any reservation with a required reason;
- extend or end an active booking;
- temporarily release or block a room;
- start or stop the presentation entitlement for operational support;
- inspect the complete audit history.

This capability grants no access to member administration, finances, support notes, narrowcasting
content management or device credentials.

### Administrator

An administrator can do everything an accommodation manager can and additionally:

- create, edit, archive and reorder rooms;
- configure opening hours and booking limits;
- link a room to a Club TV display;
- enable reservation-controlled presentation per room;
- configure the advance-access and extension increments.

### Authorized presenter

An authorized presenter is the reservation holder or another approved user explicitly added to the
reservation. This status grants screen access for that reservation only. It grants no right to edit
or cancel the reservation unless the user is also its holder or an accommodation manager.

### Rondo Player

The player receives only its own room/display state: whether a booking is active, the access-window
boundaries and the presentation-session data it already needs. It does not receive member contact
details, private notes or the accommodation-management audit trail.

## 6. Information model

All persistent records use WordPress-native storage and the Rondo field registry. No custom tables
are introduced.

### `rondo_room` custom post type

Represents one reservable physical room.

Core fields:

| Field | Meaning |
|---|---|
| `name` | Public room name |
| `location` | Building, floor or area |
| `description` | Short member-facing description |
| `capacity` | Maximum number of people |
| `facilities` | Structured list such as screen, whiteboard and wheelchair access |
| `booking_enabled` | Whether members may reserve the room |
| `display_id` | Optional relation to one `rondo_display` |
| `presentation_controlled` | Whether reservations govern screen access |
| `opening_hours` | Weekly reservable windows |
| `minimum_duration_minutes` | Default 30 minutes |
| `maximum_duration_minutes` | Default 240 minutes |
| `booking_interval_minutes` | Default 15 minutes |
| `minimum_notice_minutes` | Default 0 minutes |
| `maximum_advance_days` | Default 90 days |
| `changeover_buffer_minutes` | Default 0 minutes; blocks adjacent bookings when non-zero |
| `access_before_minutes` | Default 5 minutes |
| `extension_increment_minutes` | Default 15 minutes |
| `sort_order` | Stable order in member and manager views |
| `member_instructions` | Arrival, cleanup or key instructions |

Archiving a room prevents new reservations but preserves old bookings and audit history.

### `rondo_room_booking` custom post type

Represents one authoritative reservation for one room.

Core fields:

| Field | Meaning |
|---|---|
| `room_id` | Required relation to one `rondo_room` |
| `start_datetime` | Required timezone-aware start |
| `end_datetime` | Required timezone-aware end |
| `booking_type` | Required: `member_reservation` or `management_block` |
| `purpose` | Short visible purpose for holder and managers |
| `private_notes` | Optional, visible only to holder and accommodation managers |
| `holder_user_id` | Rondo account that owns the reservation; empty only for a management room block |
| `holder_person_id` | Linked person record for identity and contact display; empty only for a management room block |
| `booking_context_type` | Required for a member reservation: `commissie` or `age_group`; empty for a management room block |
| `commissie_id` | Required exact `commissie` relation when the context type is `commissie` |
| `age_group_key` | Required normalized year-group key such as `O12` when the context type is `age_group` |
| `context_label_snapshot` | Human-readable group name retained for historical display |
| `eligibility_team_id` | Team that established year-group eligibility at creation time, when applicable |
| `authorized_presenter_user_ids` | Additional approved Rondo accounts allowed to present |
| `status` | `confirmed`, `cancelled` or `completed` |
| `cancelled_at` | Cancellation timestamp |
| `cancelled_by_user_id` | Actor that cancelled the booking |
| `cancellation_reason` | Required for manager cancellation |
| `created_by_user_id` | Actor that created the booking |
| `last_changed_by_user_id` | Actor responsible for the latest change |
| `original_end_datetime` | Scheduled end before any live extensions |
| `extended_until` | Current approved end after extensions, when applicable |

The post author is attribution only and is not used as the sole authorization decision.

### Audit history

Use WordPress comments with a dedicated booking-activity comment type. Each immutable entry stores:

- booking ID;
- action: created, edited, cancelled, extended, presentation started, presentation stopped;
- actor user ID;
- timestamp;
- changed field names;
- sanitized before and after values where appropriate;
- optional manager reason.

Passwords, presentation tokens, device secrets and full member payloads are never logged. A
cancelled reservation is retained with status `cancelled`; normal UI actions never permanently
delete it.

## 7. Booking-context eligibility

The server derives the groups for which the current user may reserve. A client submits the selected
group, but the server never trusts that choice without repeating the eligibility check.

### Commissie eligibility

A commissie qualifies when the user's linked person has a current `work_history` position that:

- is classified as a volunteer position by the existing `VolunteerStatus` rules;
- links to that exact `commissie` post;
- is current on the day the reservation is created.

### Year-group eligibility

A year group qualifies when the user's linked person has a current volunteer position linked to a
team whose current player roster belongs to that year group. Rondo determines this from the players'
canonical `leeftijdsgroep` values and does not parse team names. Sportlink labels with the same age
number are normalized to one year-group key, for example `Onder 12` and `Onder 12 Meiden` become
`O12`.

Player positions never qualify. A trainer, leader, coach or other role qualifies only when the
existing volunteer-role classification marks it as a volunteer position.

The create form receives only the user's eligible booking contexts. Creation performs the same
check again inside the server-side write flow. Changing a booking's commissie or year group repeats
the check. A later role change does not automatically cancel an already confirmed reservation, but
the former volunteer cannot create another reservation for that group.

### Manager-created reservations

For a member reservation created by an accommodation manager, Rondo resolves eligibility against
the selected holder's linked person record, not against the manager. The manager first selects an
approved holder and then receives only that holder's eligible commissies and year groups. Creation,
changing the holder or changing the booking context repeats the holder-eligibility check on the
server. The manager capability does not bypass a failed eligibility check.

A manager may still change the room or time, cancel or operationally extend an existing reservation
after the holder's qualifying role has ended. Reassigning that reservation to another holder or
booking context requires the new combination to be eligible. A management room block has no holder
or booking context and is exempt from group eligibility.

## 8. Availability and conflict rules

### Canonical interval rule

Bookings use half-open intervals: `[start, end)`. A booking ending at 20:00 does not conflict with
one starting at 20:00 unless the room has a non-zero changeover buffer.

Rondo accepts a booking only when:

- the room exists, is active and is bookable;
- start is before end;
- duration and interval comply with room settings;
- the full interval falls within the room's opening hours;
- the booking is within the notice and advance limits;
- no confirmed booking overlaps the requested interval plus any configured buffer.

Accommodation managers may bypass notice, advance and duration limits with a visible warning. They
may not silently create an overlap. To resolve an overlap they must first move or cancel the
conflicting booking.

### Concurrent submissions

Conflict checking and creation run inside a short room-specific write lock, following Rondo's
existing option-lock pattern. A second simultaneous request receives HTTP 409 with the conflicting
availability window and must never overwrite the first booking.

### Room blocks

Accommodation managers can block a room for maintenance or a private event by creating a booking
without a member holder and marking it as a management block. Ordinary users see it as occupied.

## 9. Member experience

### Find a room

The **Ruimtes** page opens on the current day and shows:

- a date selector;
- room filters for capacity and facilities;
- a compact availability timeline per room;
- the next available time;
- a clear **Reserveren** action.

Occupied blocks do not reveal another holder's identity or purpose.

### Create a reservation

1. The member selects the eligible commissie or year group for which they are reserving.
2. The member selects a room, date, start and end.
3. Rondo validates availability immediately.
4. The member enters a short purpose and optional private notes.
5. The member may add approved Rondo users as authorized presenters.
6. A final summary shows group, room, time, presentation availability and room instructions.
7. On confirmation, Rondo repeats the eligibility check, performs a fresh locked conflict check and
   creates the booking.
8. The member sees the confirmed reservation and receives a notification with an iCalendar link.

### My reservations

The member sees upcoming and past reservations. Upcoming items expose:

- room, date, time and purpose;
- edit and cancel actions while the booking has not begun;
- authorized presenters;
- presentation status;
- a **Presenteren** action when the access window is active;
- an **Verleng 15 minuten** action when an extension is available.

### Edit or cancel

Changing room or time repeats the locked conflict check. Cancelling asks for confirmation and keeps
the record in history. A member cannot edit a completed or cancelled reservation.

## 10. Accommodation-management overview

Add **Accommodatie** to the main navigation for users with `accommodatiebeheer`.

### Day view

The default view shows one horizontal timeline per room with:

- reservation holder;
- commissie or year group;
- purpose;
- start and end time;
- status;
- whether presentation access is active;
- gap or overlap warnings;
- a quick action to open the reservation.

A current-time line and **Nu bezig** styling make the overview usable operationally.

### Week view

The week view groups reservations by day and room. It prioritizes scanability over full detail and
opens the same reservation drawer used by the day view.

### Filters and actions

Managers can filter by date, room, holder, status and presentation state. From the overview they can:

- add a reservation;
- open and edit a reservation;
- cancel with a required reason;
- extend or end a current reservation;
- block or release a room;
- contact the holder through the contact data already available to the manager;
- inspect audit history.

Drag-and-drop rescheduling is excluded from v1 because an accidental move has operational impact.
Editing uses an explicit form and confirmation summary.

## 11. Reservation-controlled presentation

### Room and display relationship

A room can have zero or one assigned `rondo_display`; one display can belong to at most one active
room. A room without a display remains reservable but shows **Geen presentatiescherm beschikbaar**.

The existing per-display `presentation_enabled` switch remains the low-level safety switch. A room
offers reservation-controlled presentation only when:

- it has an assigned display;
- that display has browser presentations enabled;
- `presentation_controlled` is enabled for the room.

### Access window

By default, access starts five minutes before the reservation and ends at the effective reservation
end. The server derives this window from the booking; clients never decide it themselves.

During the access window:

1. The TV shows that the room is reserved and displays a short-lived presentation code.
2. The holder or an authorized presenter opens `/presenteren` and enters the code.
3. Rondo verifies the code, display-room relation, active booking and user entitlement.
4. The browser then offers its native tab, window or screen picker.
5. The TV pauses Club TV playback and shows the WebRTC stream.

The TV shows reservation times but does not show the holder's name by default. A later room setting
may make names visible when a club explicitly wants that.

### Code and token rules

- The presentation code proves which physical screen the user is addressing.
- The signed-in account proves who the user is.
- The active reservation proves whether that user may control the screen now.
- A correct code without an eligible reservation returns a neutral denial.
- Participant tokens expire no later than the effective booking end.
- Starting a new authorized presentation replaces the previous presentation for that display.
- An accommodation manager may explicitly take over or stop a stream for support.

### Ending and extending

At the effective end time:

- the sender gets an advance warning;
- Rondo stops the presentation entitlement;
- the receiver closes the stream and resumes Club TV;
- a following reservation receives control at its own access boundary.

When no confirmed booking follows and the room remains within opening hours, the holder can extend
in the configured increment, default 15 minutes. Each extension performs a fresh locked conflict
check. The extension may continue only until the earlier of:

- the next booking's start minus its changeover buffer;
- the room's closing time;
- the room's configured maximum continuous duration.

The extension changes the effective booking end and is written to the audit history. Screen access
never continues merely because an old WebRTC connection remains open.

### Degraded operation

If Rondo is unreachable, Club TV keeps its existing offline behavior. A new presentation cannot be
authorized while entitlement cannot be verified. An already running presentation ends locally at
the last verified entitlement boundary. The player then returns to its cached playlist.

## 12. Notifications

The holder receives a notification when:

- a booking is created for them;
- its room, start or end changes;
- it is cancelled;
- an accommodation manager extends or ends it.

The notification names the acting manager when applicable and contains the current canonical room
and time. Email is the v1 delivery channel; the confirmation screen is the in-app confirmation.
Missing email does not block a manager from creating the booking, but the UI warns that no email was
sent.

Adding other presenters notifies those presenters with room, time and a link back to the booking.
Private notes and manager-only audit details are not included in email.

## 13. Planned REST surface

All routes live under `/rondo/v1/rooms` and use canonical field names.

| Method and route | Permission | Purpose |
|---|---|---|
| `GET /rooms` | Logged-in user | List rooms and safe member-facing configuration |
| `GET /rooms/availability` | Logged-in user | Return occupied/free intervals without private holder data |
| `GET /rooms/booking-contexts` | Logged-in user | List only the current user's eligible commissies and year groups |
| `GET /rooms/bookings/mine` | Logged-in user | List the current user's full reservations |
| `POST /rooms/bookings` | Logged-in user | Create an own reservation |
| `GET /rooms/bookings/{id}` | Holder, presenter or manager | Read the allowed booking representation |
| `POST /rooms/bookings/{id}` | Holder or manager | Edit an allowed reservation |
| `POST /rooms/bookings/{id}/cancel` | Holder or manager | Cancel with attribution |
| `POST /rooms/bookings/{id}/extend` | Holder or manager | Extend after a locked availability check |
| `GET /rooms/manage/bookings` | Accommodation manager | Full operational day/week dataset |
| `GET /rooms/manage/booking-contexts?holder_user_id={id}` | Accommodation manager | List only the selected holder's eligible commissies and year groups |
| `POST /rooms/manage/bookings` | Accommodation manager | Create a qualifying-holder reservation or a holderless room block |
| `GET`, `POST /rooms/manage/{id}` | Administrator | Read or update room configuration |
| `GET /rooms/bookings/{id}/activity` | Accommodation manager | Read immutable booking activity |

The presentation join route gains booking-entitlement validation. Device-token routes remain under
the existing narrowcasting namespace.

## 14. Privacy and security

- Ordinary availability responses contain no holder name, contact details, purpose or notes.
- A holder sees their own booking; an authorized presenter sees only the details needed to present.
- Accommodation managers see holder identity and operational contact data but gain no unrelated
  person fields.
- All writes use nonces, capability checks and strict field validation.
- Time, room and holder IDs are revalidated server-side; client-provided entitlement is ignored.
- Booking-context eligibility is derived from the linked person and current `work_history`; a client-
  supplied commissie, year group or team never grants permission by itself.
- Presentation tokens are random, hashed at rest and bounded by the booking window.
- Reservation and signaling endpoints are rate-limited per user or device as appropriate.
- Cancelled bookings and activity records are not exposed through public WordPress REST routes.
- Permanent deletion is restricted to administrators and is not offered in the normal UI.

## 15. Edge cases

| Situation | Required behavior |
|---|---|
| Two people submit the last free slot together | One succeeds; one receives a 409 conflict |
| Manager moves a booking into an occupied slot | Refuse and identify the conflicting interval |
| Display is offline | Booking remains valid; UI says presentation screen is offline |
| Room has no display | Reservation works; presentation is unavailable |
| User enters the right screen code without a booking | Deny without revealing holder identity |
| Current booking runs into the next booking | End at the boundary; no extension offered |
| Next booking is cancelled | Current holder may request an extension after availability refresh |
| Manager cancels an active booking | Stop entitlement and stream, then resume Club TV |
| Holder's account is disabled | Booking remains visible to managers; presentation entitlement is denied |
| Manager selects an ineligible holder and booking-context combination | Reject it; manager capability does not bypass eligibility |
| User has only a player position in the selected team | Do not offer or accept that year group |
| Team has players from more than one normalized year group | Return every derived year group and retain the exact selected key |
| Volunteer role ends after a future booking was confirmed | Keep the booking, but deny new bookings or context changes for that group |
| Team has no current players with a usable `leeftijdsgroep` | Do not derive a year group; surface the missing roster classification to managers |
| Daylight-saving transition | Use the configured site/room timezone and explicit RFC 3339 API values |
| Network drops during presentation | Receiver follows the current WebRTC recovery behavior but still enforces the verified end time |

## 16. Acceptance criteria

### Reservations

- A logged-in member can see availability but can reserve only for an eligible commissie or year
  group.
- A current volunteer in a commissie can reserve for that exact commissie.
- A current volunteer attached to an O12 team can reserve for the normalized O12 year group.
- A player without another qualifying volunteer role cannot create a reservation.
- A submitted group that was not returned by the server-side eligibility resolver is rejected.
- Another user cannot create an overlapping reservation, including under concurrent submission.
- A member sees only availability for other people's bookings and full details for their own.
- A member can edit or cancel their own future booking.
- Past and cancelled reservations remain in history.

### Accommodation management

- A user with `accommodatiebeheer` sees day and week overviews with holder names.
- A manager can create a member reservation only for a holder who qualifies for the selected
  commissie or year group.
- A manager cannot use their capability to create an ordinary member reservation for an ineligible
  holder.
- A manager can always create a management room block without a holder or booking context.
- The manager can edit, operationally extend and cancel any existing reservation.
- A manager cancellation requires a reason.
- Each action records actor, time and changed fields.
- The affected holder receives an email or a visible no-email warning is returned.

### Presentation access

- A linked screen shows a presentation code only during an eligible access window.
- The correct code is insufficient when the signed-in user is not an authorized presenter.
- An eligible user can share a browser tab, window or screen with the existing WebRTC flow.
- The stream stops and Club TV resumes at the effective booking end.
- Extension is offered only when the room remains free and succeeds only after a new locked check.
- A following reservation cannot be displaced by an extension or lingering connection.

## 17. Delivery milestones

### Milestone 1: Rooms, capability and manager overview

- `rondo_room` and `rondo_room_booking` post types and field contracts;
- `accommodatiebeheer` capability and role-matrix integration;
- room administration;
- manager day/week overview;
- manager add, edit and cancel;
- conflict locking, audit history and notifications;
- booking-context fields and manager-visible commissie/year-group labels.

### Milestone 2: Member self-service

- room availability;
- server-derived eligible commissie and year-group choices;
- create, edit and cancel own bookings;
- My reservations;
- authorized presenters;
- iCalendar download.

### Milestone 3: Reservation-controlled screens

- room-to-display assignment;
- entitlement-aware presentation codes and tokens;
- access-boundary enforcement on sender and receiver;
- stop, takeover and return-to-Club-TV behavior;
- extension flow.

### Milestone 4: Pilot hardening

- production metrics and operational warnings;
- two-device testing on the actual club network;
- timezone and concurrency tests;
- accessibility and responsive verification;
- manager and member UAT.

### Later milestones

- TURN for guest-network and VLAN-separated use;
- optional Google Calendar or Microsoft 365 synchronization;
- recurring bookings;
- configurable approval workflows.

## 18. Recommended implementation order

Build the manager workflow before member self-service. It establishes room configuration, conflict
rules and operational corrections before ordinary users create data. Connect presentation access
only after reservation authorization is stable; otherwise the screen becomes a second source of
booking truth.

The first implementation review should therefore approve Milestone 1 as the next coding scope.
