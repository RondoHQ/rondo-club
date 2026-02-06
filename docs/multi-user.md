# Multi-User System

This document describes Rondo Club's multi-user setup and user management.

## Overview

Rondo Club uses a **shared access model**: all approved users can see and edit all data. This makes it ideal for teams collaborating on a shared contact database.

**Key features:**

- Multiple users can access the same Rondo Club installation
- All approved users see all contacts, teams, dates, and todos
- New users require administrator approval before accessing data
- User activity is tracked via post author and note author fields

## User Approval

New users cannot access any data until an administrator approves them.

### Approving Users

1. Go to **Settings > User Approval** in Rondo Club
2. Review pending users
3. Click **Approve** to grant access

Alternatively, use WP-CLI:

```bash
wp stadion approve-user <user_id>
```

### Approval Status

| Status | Can Access Data |
|--------|-----------------|
| Approved | Yes - full access to all data |
| Pending | No - sees empty lists |
| Administrators | Always approved automatically |

## User Roles

### Rondo User Role

Rondo Club creates a custom WordPress role called **"Rondo User"** (`stadion_user`).

**Capabilities:**

- Create, edit, and delete people, teams, dates
- Upload files (photos, logos)
- Access the Rondo Club frontend

**Restrictions:**

- Cannot access WordPress admin settings
- Cannot manage other users
- Cannot install plugins or themes

### Administrator Access

WordPress administrators (`manage_options` capability):

- Always approved automatically
- Full access in both frontend and WordPress admin
- Can approve/reject other users

## Collaborative Features

### Shared Data

All approved users share:

- **People** - All contact records
- **Teams** - All team/company records
- **Todos** - All todo items
- **Notes** - Shared notes on contacts (private notes remain private)

### Note Privacy

Notes can be marked as private or shared:

- **Shared notes** - Visible to all users viewing the contact
- **Private notes** - Only visible to the author

Toggle visibility when creating or editing a note.

### Activity Tracking

The system tracks:

- **Post author** - Who created a contact, team, or date
- **Note author** - Who wrote a note
- **Activity timestamps** - When changes were made

This information is visible in the UI for accountability.

## Daily Digest

The daily reminder email includes:

- Upcoming birthdays
- Overdue todos
- Recent activity on contacts (notes added in last 24 hours)

Configure digest delivery in **Settings > Notifications**.

## Related Documentation

- [Access Control](./access-control.md) - Permission system details
- [Data Model](./data-model.md) - Post types and field definitions
- [REST API](./rest-api.md) - API endpoints
