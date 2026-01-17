---
created: 2026-01-16T19:19
title: Add Slack DM support to channel selector
area: ui
files:
  - src/pages/Settings/SlackSettings.jsx
  - includes/class-rest-slack.php
---

## Problem

The Connections -> Slack panel only shows Channels in the dropdown, not People to send DMs to. Users may want to receive notifications via DM instead of posting to a channel.

## Solution

1. Extend the Slack API integration to fetch users list (conversations.list with types=im or users.list)
2. Add users/DMs as options in the channel selector dropdown
3. May need to handle DM channel IDs differently when sending messages
