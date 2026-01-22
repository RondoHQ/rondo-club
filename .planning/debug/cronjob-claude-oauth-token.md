---
status: investigating
trigger: "Cronjob runs get-feedback.sh but Claude hangs indefinitely instead of processing feedback"
created: 2026-01-22T10:30:00Z
updated: 2026-01-22T10:45:00Z
---

## Current Focus

hypothesis: Claude hangs when HOME env var points to non-existent directory; cron may set HOME incorrectly before script can override it
test: Check what HOME value cron passes, verify script's HOME override happens before Claude invocation
expecting: If cron sets wrong HOME, Claude hangs; if script correctly overrides HOME, should work
next_action: Add debugging to script to log actual HOME value when Claude is invoked

## Symptoms

expected: Claude Code processes feedback items from the API and returns results
actual: Claude starts running but hangs indefinitely without producing output, eventually killed with exit code 137 (SIGKILL)
errors: [2026-01-22 06:26:59] [ERROR] Claude session failed (exit code: 137) - Claude output was empty
reproduction: Run `bin/get-feedback.sh --run` from cron or launchd environment
started: User set up the cron job recently, added CLAUDE_CODE_OAUTH_TOKEN to .env file

## Eliminated

- hypothesis: OAuth token not being read from .env
  evidence: Token is correctly exported via `set -a; source .env; set +a` pattern
  timestamp: 2026-01-22T10:35:00Z

- hypothesis: HOMEBREW_PATH tilde not expanding in cron
  evidence: Bash falls back to /etc/passwd for tilde expansion when HOME unset; expansion works correctly
  timestamp: 2026-01-22T10:38:00Z

- hypothesis: Credentials file needed for auth
  evidence: CLAUDE_CODE_OAUTH_TOKEN env var works for auth when HOME is correct
  timestamp: 2026-01-22T10:40:00Z

## Evidence

- timestamp: 2026-01-22T10:33:00Z
  checked: Log file analysis
  found: Script worked successfully on Jan 21 21:40-22:00 (status=new), then started hanging after 22:15 (status=approved). Exit code 137 = SIGKILL after hanging for hours.
  implication: Something changed around 22:15 or the behavior is intermittent

- timestamp: 2026-01-22T10:36:00Z
  checked: CLAUDE_CODE_OAUTH_TOKEN in minimal env
  found: Claude --version works with token in minimal env; Claude --print works with correct HOME
  implication: Token auth works, but something else is wrong

- timestamp: 2026-01-22T10:40:00Z
  checked: Claude behavior with wrong HOME
  found: When HOME points to non-existent directory, Claude hangs indefinitely (exit 142 from SIGALRM timeout). When HOME is correct or unset, Claude works.
  implication: HOME must point to existing directory for Claude to function

- timestamp: 2026-01-22T10:42:00Z
  checked: Script simulation
  found: Full script simulation with env -i + source .env works correctly - HOME ends up as /Users/joostdevalk and Claude responds
  implication: Script logic is correct when executed properly; issue may be in how cron invokes it

## Resolution

root_cause: [INVESTIGATING] Likely cron passes incorrect HOME before script can override
fix:
verification:
files_changed: []
