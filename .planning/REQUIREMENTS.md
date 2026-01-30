# Requirements: Stadion v11.0 VOG Management

**Defined:** 2026-01-30
**Core Value:** Streamline VOG compliance for volunteer management

## v11.0 Requirements

Requirements for VOG management system. Each maps to roadmap phases.

### Navigation & List

- [ ] **VOG-01**: User sees VOG section in sidebar navigation
- [ ] **VOG-02**: User sees filtered list of volunteers needing VOG (huidig-vrijwilliger=true AND no datum-vog OR datum-vog 3+ years ago)
- [ ] **VOG-03**: List displays columns: Name, KNVB ID, Email, Phone, Datum VOG

### Bulk Actions

- [ ] **BULK-01**: User can select multiple people in VOG list
- [ ] **BULK-02**: User can send VOG email to selected people
- [ ] **BULK-03**: User can mark "VOG requested" for selected people (records date)

### Email System

- [ ] **EMAIL-01**: System sends VOG emails via wp_mail()
- [ ] **EMAIL-02**: New volunteer email template with `{first_name}` variable
- [ ] **EMAIL-03**: Renewal email template with `{first_name}` and `{previous_vog_date}` variables
- [ ] **EMAIL-04**: System automatically selects correct template based on volunteer status

### Settings

- [ ] **SET-01**: User can configure from email address for VOG emails
- [ ] **SET-02**: User can configure new volunteer email template text
- [ ] **SET-03**: User can configure renewal email template text

### Tracking

- [ ] **TRACK-01**: System records when VOG email was sent to each person
- [ ] **TRACK-02**: User can filter VOG list by email status (sent/not sent)
- [ ] **TRACK-03**: User can see email history on person profile

## Future Requirements

None identified for this milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| External VOG system integration | Just tracking internally, manual process with Justis |
| Automated VOG status checking | Manual update via datum-vog field or Sportlink sync |
| Email delivery tracking (opened/bounced) | wp_mail doesn't support this natively |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| VOG-01 | TBD | Pending |
| VOG-02 | TBD | Pending |
| VOG-03 | TBD | Pending |
| BULK-01 | TBD | Pending |
| BULK-02 | TBD | Pending |
| BULK-03 | TBD | Pending |
| EMAIL-01 | TBD | Pending |
| EMAIL-02 | TBD | Pending |
| EMAIL-03 | TBD | Pending |
| EMAIL-04 | TBD | Pending |
| SET-01 | TBD | Pending |
| SET-02 | TBD | Pending |
| SET-03 | TBD | Pending |
| TRACK-01 | TBD | Pending |
| TRACK-02 | TBD | Pending |
| TRACK-03 | TBD | Pending |

**Coverage:**
- v11.0 requirements: 16 total
- Mapped to phases: 0
- Unmapped: 16

---
*Requirements defined: 2026-01-30*
*Last updated: 2026-01-30 after initial definition*
