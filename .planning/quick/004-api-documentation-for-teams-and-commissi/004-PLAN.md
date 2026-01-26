# Quick Task 004: API Documentation for Teams and Commissies

## Goal

Create comprehensive API documentation for the Teams and Commissies (Committees) endpoints, following the established format from `api-leden-crud.md`.

## Tasks

### Task 1: Create Teams API Documentation

Create `docs/api-teams.md` with:
- All CRUD endpoints (`/wp/v2/teams`)
- Field reference (title, website, contact_info repeater)
- Logo upload endpoints (`/stadion/v1/teams/{id}/logo/upload`, `/stadion/v1/teams/{id}/logo`)
- People by team endpoint (`/stadion/v1/teams/{team_id}/people`)
- Direct sharing endpoints
- Code examples (JavaScript, PHP, cURL)

### Task 2: Create Commissies API Documentation

Create `docs/api-commissies.md` with:
- All CRUD endpoints (`/wp/v2/commissies`)
- Field reference (title, editor/description, website, contact_info repeater)
- Hierarchical structure support (parent-child)
- Code examples (JavaScript, PHP, cURL)

## Acceptance Criteria

- [ ] `docs/api-teams.md` created with complete Teams API documentation
- [ ] `docs/api-commissies.md` created with complete Commissies API documentation
- [ ] Both documents follow the established format from `api-leden-crud.md`
- [ ] All relevant endpoints from `rest-api.md` are documented
- [ ] Code examples work with Application Password and Nonce authentication
