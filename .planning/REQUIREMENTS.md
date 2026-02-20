# Requirements: Rondo Club v30.0 User Accounts & Profiles

**Defined:** 2026-02-20
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v30.0 Requirements

Requirements for milestone v30.0. Each maps to roadmap phases.

### Access Control

- [ ] **ACCS-01**: Non-admin users are redirected away from wp-admin to the app home page
- [ ] **ACCS-02**: WP admin blocking exempts admin-ajax.php, WP-CLI, and cron requests so existing functionality is unaffected

### User Provisioning

- [ ] **PROV-01**: Admin can create a WordPress user account from a Sportlink person record
- [ ] **PROV-02**: New user receives a branded welcome email with a password-set link valid for 7 days
- [ ] **PROV-03**: User account is linked bidirectionally to the person record (user stores person ID, person stores user ID)
- [ ] **PROV-04**: User's KNVB ID is stored in user meta for sync lookup (prevents duplicate accounts on email change)
- [ ] **PROV-05**: Welcome email is only sent once per user even if provisioning is re-triggered
- [ ] **PROV-06**: Admin can customize the welcome email subject and body text in Settings

### Capability Mapping

- [ ] **CAPS-01**: Admin can configure a mapping from Sportlink Functies to Rondo roles via Settings
- [ ] **CAPS-02**: Functie-to-role mapping is displayed as a visual matrix UI (Functies as rows, roles as columns)
- [ ] **CAPS-03**: Known Functies are populated automatically by rondo-sync so admin does not type names manually
- [ ] **CAPS-04**: Capabilities are automatically assigned from Sportlink Functies during sync with full reconciliation (grant and revoke)
- [ ] **CAPS-05**: Admin can manually override a user's capabilities independent of their Functies
- [ ] **CAPS-06**: Manual capability overrides survive automatic Functie-based sync
- [ ] **CAPS-07**: Administrator users are never modified by automatic capability sync
- [ ] **CAPS-08**: Admin can trigger a sync-all-capabilities action on demand to re-apply the current mapping

### Profile

- [ ] **PROF-01**: User can change their password from an in-app profile page
- [ ] **PROF-02**: Password change requires verification of the current password before accepting a new one
- [ ] **PROF-03**: User is redirected to login after a successful password change due to session invalidation
- [ ] **PROF-04**: Profile page displays the user's linked Sportlink name and active Functies

### Avatar

- [ ] **AVTR-01**: User avatar in the app sidebar shows the linked Sportlink person photo when available
- [ ] **AVTR-02**: Users without a linked person or without a photo see a default avatar icon

## Future Requirements

Deferred to a future milestone. Tracked but not in current roadmap.

### User Management

- **UMGT-01**: Admin can view a paginated list of all provisioned users in Settings
- **UMGT-02**: rondo-sync can auto-provision users for all members with email addresses (opt-in flag)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Self-registration / public signup | Sportlink is the source of truth for membership; no approval flow or GDPR consent in scope |
| Email-based login (wp-login.php) | Members use password-set link from welcome email; WordPress username is internal |
| Role hierarchy (rondo_bestuur inherits rondo_fairplay) | 4 existing roles + per-user add_cap() covers all cases; hierarchies add complexity with no benefit |
| Bulk user creation from CSV | Bypasses Sportlink-to-person sync chain; breaks the KNVB ID link needed for auto-sync |
| Profile editing (name, email, bio) | All member data comes from Sportlink; only password is user-editable |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| — | — | — |

**Coverage:**
- v30.0 requirements: 20 total
- Mapped to phases: 0
- Unmapped: 20

---
*Requirements defined: 2026-02-20*
*Last updated: 2026-02-20 after initial definition*
