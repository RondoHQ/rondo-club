# Roadmap: Caelis Tech Debt Cleanup

## Overview

This milestone focuses on cleaning up technical debt in the Caelis personal CRM. The work progresses from splitting the monolithic REST API class into domain-specific files, then hardening security, and finally cleaning up code quality issues. All changes maintain full backward compatibility.

## Domain Expertise

None

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 1: REST API Infrastructure** - Extract base class and create domain-specific file structure ✓
- [x] **Phase 2: REST API People & Companies** - Extract people and company endpoints ✓
- [x] **Phase 3: REST API Integrations** - Extract Slack and import/export endpoints ✓
- [x] **Phase 4: Security Hardening** - Encrypt tokens, validate URLs, review public endpoints ✓
- [x] **Phase 5: XSS Protection** - Add server-side sanitization using WordPress functions ✓
- [x] **Phase 6: Code Cleanup** - Remove console.error, create .env.example, consolidate utilities ✓

## Phase Details

### Phase 1: REST API Infrastructure
**Goal**: Create the foundation for the REST API split by extracting a base class and establishing the new file structure
**Depends on**: Nothing (first phase)
**Research**: Unlikely (internal refactoring, established WordPress patterns)
**Plans**: TBD

Plans:
- [x] 01-01: Create base REST class and routing infrastructure ✓
- [x] 01-02: Verify backward compatibility with existing endpoints ✓

### Phase 2: REST API People & Companies
**Goal**: Extract people and company endpoints into dedicated classes
**Depends on**: Phase 1
**Research**: Unlikely (moving existing code, no new patterns)
**Plans**: TBD

Plans:
- [x] 02-01: Extract people endpoints to class-rest-people.php ✓
- [x] 02-02: Extract company endpoints to class-rest-companies.php ✓

### Phase 3: REST API Integrations
**Goal**: Extract Slack integration and import/export endpoints into dedicated classes
**Depends on**: Phase 2
**Research**: Unlikely (moving existing code, no new patterns)
**Plans**: TBD

Plans:
- [x] 03-01: Extract Slack integration to class-rest-slack.php ✓
- [x] 03-02: Extract import/export to class-rest-import-export.php ✓

### Phase 4: Security Hardening
**Goal**: Improve security by encrypting tokens, validating webhook URLs, and documenting public endpoints
**Depends on**: Phase 3
**Research**: Likely (sodium encryption patterns)
**Research topics**: PHP sodium_crypto_secretbox usage, key management in WordPress
**Plans**: TBD

Plans:
- [x] 04-01: Implement sodium encryption for Slack tokens ✓
- [x] 04-02: Add webhook URL validation and review public endpoints ✓

### Phase 5: XSS Protection
**Goal**: Add server-side sanitization to prevent XSS attacks
**Depends on**: Phase 4
**Research**: Unlikely (WordPress sanitization functions well-documented)
**Plans**: TBD

Plans:
- [x] 05-01: Add wp_kses sanitization to REST API responses ✓

### Phase 6: Code Cleanup
**Goal**: Remove debug logging, document environment variables, consolidate duplicated code
**Depends on**: Phase 5
**Research**: Unlikely (straightforward cleanup tasks)
**Plans**: TBD

Plans:
- [x] 06-01: Remove console.error() calls from React components ✓
- [x] 06-02: Create .env.example and consolidate decodeHtml() ✓

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. REST API Infrastructure | 2/2 | Complete ✓ | 2026-01-13 |
| 2. REST API People & Companies | 2/2 | Complete ✓ | 2026-01-13 |
| 3. REST API Integrations | 2/2 | Complete ✓ | 2026-01-13 |
| 4. Security Hardening | 2/2 | Complete ✓ | 2026-01-13 |
| 5. XSS Protection | 1/1 | Complete ✓ | 2026-01-13 |
| 6. Code Cleanup | 2/2 | Complete ✓ | 2026-01-13 |
