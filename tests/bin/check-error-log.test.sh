#!/bin/bash
#
# Tests for bin/check-error-log.sh — specifically the noise filter, which is the
# part that decides whether a run looks like "clean" or "something is wrong".
#
# Runs entirely offline via --filter-stdin; no SSH, no .env, no production.
#
# Usage: tests/bin/check-error-log.test.sh
#

set -uo pipefail

SCRIPT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/bin/check-error-log.sh"

passed=0
failed=0

assert_eq() {
	local name="$1" expected="$2" actual="$3"
	if [ "$expected" = "$actual" ]; then
		passed=$((passed + 1))
		echo "  ok — $name"
	else
		failed=$((failed + 1))
		echo "  FAIL — $name"
		echo "    expected: $(printf '%q' "$expected")"
		echo "    actual:   $(printf '%q' "$actual")"
	fi
}

echo "check-error-log filter tests"

# The regression that started this: WP core logs an indented "Automatic plugin
# updates" pair that the old `grep -v 'Automatic updates'` did not match, so 136
# noise lines were reported as findings.
out=$(printf '%s\n' \
	'[08-Aug-2026 01:45:02 UTC] Automatic updates starting...' \
	'[08-Aug-2026 01:45:02 UTC]   Automatic plugin updates starting...' \
	'[08-Aug-2026 01:45:02 UTC]   Automatic plugin updates complete.' \
	'[08-Aug-2026 01:45:03 UTC] Automatic updates complete.' \
	| "$SCRIPT" --filter-stdin)
assert_eq "automatic update lines are all noise" "" "$out"

# Theme cron chatter listed in the filters.
out=$(printf '%s\n' \
	'[07-Aug-2026 02:00:01 UTC] [Rondo Fee Cache] Rebuilt 412 entries.' \
	'[07-Aug-2026 02:00:02 UTC] [Rondo Volunteer] Expanded 5 shift(s) between 2026-08-07 and 2026-08-14.' \
	| "$SCRIPT" --filter-stdin)
assert_eq "theme cron chatter is noise" "" "$out"

# Real errors must survive.
fatal='[03-Aug-2026 08:42:47 UTC] PHP Fatal error:  Uncaught TypeError: Rondo\REST\MemberShifts::format_shift_summary(): Argument #1 ($shift) must be of type WP_Post, null given'
out=$(printf '%s\n' \
	'[03-Aug-2026 08:00:00 UTC] Automatic updates starting...' \
	"$fatal" \
	'[03-Aug-2026 08:00:01 UTC] Automatic updates complete.' \
	| "$SCRIPT" --filter-stdin)
assert_eq "a fatal survives the filter" "$fatal" "$out"

rest='[05-Aug-2026 10:11:12 UTC] REST API error: GET /rondo/v1/people/filtered — Ongeldige parameter(s): orderby (code: rest_invalid_param)'
out=$(printf '%s\n' "$rest" | "$SCRIPT" --filter-stdin)
assert_eq "a REST API error survives the filter" "$rest" "$out"

# Filter patterns are literals, not regexes — the bracketed ones must not be
# read as character classes.
out=$(printf '%s\n' '[08-Aug-2026 00:00:00 UTC] Rondo Fee Cache without brackets' | "$SCRIPT" --filter-stdin)
assert_eq "bracketed filters match literally, not as regex" \
	'[08-Aug-2026 00:00:00 UTC] Rondo Fee Cache without brackets' "$out"

# An all-noise input is a successful, empty run — not a failure. This is the
# grep-exits-1 trap that made a quiet day look like a broken check.
printf '%s\n' '[08-Aug-2026 01:45:02 UTC] Automatic updates starting...' | "$SCRIPT" --filter-stdin >/dev/null
assert_eq "all-noise input still exits 0" "0" "$?"

# Extra filters compose with the defaults.
out=$(printf '%s\n' \
	'[08-Aug-2026 00:00:00 UTC] Automatic updates complete.' \
	'[08-Aug-2026 00:00:01 UTC] [InvoiceReminderScheduler] Invoice 8637: reminder 1 sent.' \
	'[08-Aug-2026 00:00:02 UTC] PHP Fatal error:  boom' \
	| "$SCRIPT" --filter-stdin --exclude '[InvoiceReminderScheduler]')
assert_eq "--exclude adds to the defaults" '[08-Aug-2026 00:00:02 UTC] PHP Fatal error:  boom' "$out"

# A bad --since must fail loudly rather than silently scanning the default window.
"$SCRIPT" --since 'gisteren' >/dev/null 2>&1
assert_eq "invalid --since exits 1" "1" "$?"

echo
echo "$passed passed, $failed failed"
[ "$failed" -eq 0 ]
