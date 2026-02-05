# Roadmap: Stadion v17.0 De-AWC

## Overview

Transform Stadion from AWC-specific to club-configurable by implementing a backend configuration system for club identity (name, color, FreeScout URL), refactoring the hardcoded AWC color scheme to a dynamic club color system, and removing all AWC-specific references from code and documentation to make the theme installable by any sports club without code changes.

## Phases

**Phase Numbering:**
- Integer phases (144, 145, 146): Planned milestone work (continues from v16.0 which ended at phase 143)
- Decimal phases (144.1, 144.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 144: Backend Configuration System** - Club-wide settings stored in WordPress options with REST API
- [x] **Phase 145: Frontend & Color Refactor** - Settings UI and awc→club rename throughout codebase
- [x] **Phase 146: Integration Cleanup** - FreeScout URL configuration and documentation de-AWC

## Phase Details

### Phase 144: Backend Configuration System
**Goal**: Administrators can configure club-wide settings (name, default color, FreeScout URL) via WordPress options, exposed to frontend via REST API

**Depends on**: Nothing (first phase)

**Requirements**: CFG-01, CFG-02, CFG-03, CFG-04, CFG-05

**Success Criteria** (what must be TRUE):
  1. Admin can save club name via WordPress options (retrieved in PWA manifest and page titles)
  2. Admin can save default accent color as hex value (used as theme default)
  3. Admin can save FreeScout base URL (used for integration links)
  4. REST API endpoint exists at `/stadion/v1/config` with admin write, all-users read permissions
  5. Sensible defaults apply when no config exists (empty club name, green #006935, FreeScout hidden)

**Plans**: 1 plan

Plans:
- [x] 144-01-PLAN.md — ClubConfig service class, REST endpoint, stadionConfig extension, dynamic page title

### Phase 145: Frontend & Color Refactor
**Goal**: Users see club configuration in Settings UI and AWC color scheme becomes dynamic club color throughout application

**Depends on**: Phase 144 (needs backend API)

**Requirements**: UI-01, UI-02, UI-03, CLR-01, CLR-02, CLR-03

**Success Criteria** (what must be TRUE):
  1. Admin-only club configuration section exists in Settings with name, color picker, and FreeScout URL fields
  2. Club color appears as first option in user accent color picker, labeled "Club"
  3. Club color falls back to green (#006935) when not configured by admin
  4. Login screen, favicon SVG, and PWA manifest theme-color read default from club config API
  5. All AWC color key references renamed to 'club' (tailwind.config.js, useTheme.js, index.css, Settings.jsx)
  6. All "AWC" comments removed from source code

**Plans**: 2 plans

Plans:
- [x] 145-01-PLAN.md — AWC-to-club rename across Tailwind, CSS, useTheme, Settings; dynamic club color support
- [x] 145-02-PLAN.md — Club configuration section in Settings UI with color picker; dynamic login page styling

### Phase 146: Integration Cleanup
**Goal**: FreeScout integration reads URL from club config and all AWC/svawc.nl-specific references removed from documentation

**Depends on**: Phase 145

**Requirements**: INT-01, INT-02, INT-03

**Success Criteria** (what must be TRUE):
  1. FreeScout link in PersonDetail reads base URL from club config (hidden when URL not set)
  2. AGENTS.md documentation uses placeholder domain instead of svawc.nl references
  3. Theme installable by another club without any code changes (verified by code review)

**Plans**: 1 plan

Plans:
- [x] 146-01-PLAN.md — Externalize FreeScout URL, remove AWC references, update documentation

## Progress

**Execution Order:**
Phases execute in numeric order: 144 → 145 → 146

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 144. Backend Configuration System | 1/1 | Complete | 2026-02-05 |
| 145. Frontend & Color Refactor | 2/2 | Complete | 2026-02-05 |
| 146. Integration Cleanup | 1/1 | Complete | 2026-02-05 |
