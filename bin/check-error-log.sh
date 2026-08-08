#!/bin/bash
#
# Rondo Club production error-log check.
#
# Fetches wp-content/debug.log (plus the rotated files) from production, strips
# the known-noise lines, and prints whatever is left.
#
# Wraps the SSH + credential loading into a single command so it can be covered
# by one Claude Code permission rule instead of one rule per command shape —
# same reason as bin/wp-prod. The `rondo-error-log-check` scheduled task used to
# inline the ssh pipeline, which no permission prefix rule could match (command
# substitution plus `&&` plus a pipe), so in dontAsk mode every run was denied
# and the check reported nothing for eight days.
#
# Usage:
#   bin/check-error-log.sh                          # today + yesterday (default)
#   bin/check-error-log.sh --since 2026-07-31       # every line logged on/after that date
#   bin/check-error-log.sh --exclude '[Rondo Foo]'  # extra noise filter, repeatable
#   bin/check-error-log.sh --list-filters           # print the active noise filters
#   bin/check-error-log.sh --filter-stdin           # filter stdin instead of SSH (offline; used by tests)
#
# Exit codes:
#   0  the check RAN. Findings — possibly none — are on stdout.
#   1  the check could NOT run. Never read this as "no errors found".
#
# stdout carries log lines only. Diagnostics go to stderr, and the last stderr
# line is always a machine-readable summary:
#   check-error-log: status=ok files=9 lines_in=458 lines_out=22
#   check-error-log: status=failed reason=ssh_unreachable
#
# Requires DEPLOY_SSH_* in .env (the same entries bin/deploy.sh uses).
#

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Noise filters. Fixed strings, matched anywhere in the line.
#
# This list is authoritative — the scheduled task's state.json no longer keeps
# its own copy. WP core writes the "Automatic ... updates" pairs several times
# an hour; the old inline check filtered only 'Automatic updates', so the 136
# indented "Automatic plugin updates" lines leaked through and made every run
# look like it had findings.
DEFAULT_EXCLUDES=(
	'Automatic updates starting...'
	'Automatic updates complete.'
	'Automatic plugin updates starting...'
	'Automatic plugin updates complete.'
	'Automatic theme updates starting...'
	'Automatic theme updates complete.'
	'Automatic translation updates starting...'
	'Automatic translation updates complete.'
	'[Rondo Fee Cache]'
	'[Rondo Volunteer] Expanded'
)

SINCE=""
FILTER_STDIN=0
LIST_FILTERS=0
EXTRA_EXCLUDES=()

usage() {
	sed -n '2,32p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
	case "$1" in
		--since)
			SINCE="${2:-}"
			if ! [[ "$SINCE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
				echo "check-error-log: --since needs a YYYY-MM-DD date, got '${SINCE}'" >&2
				echo "check-error-log: status=failed reason=bad_since_argument" >&2
				exit 1
			fi
			shift 2
			;;
		--exclude)
			if [ -z "${2:-}" ]; then
				echo "check-error-log: --exclude needs a pattern" >&2
				echo "check-error-log: status=failed reason=bad_exclude_argument" >&2
				exit 1
			fi
			EXTRA_EXCLUDES+=("$2")
			shift 2
			;;
		--filter-stdin) FILTER_STDIN=1; shift ;;
		--list-filters) LIST_FILTERS=1; shift ;;
		-h|--help) usage; exit 0 ;;
		*)
			echo "check-error-log: unknown argument '$1'" >&2
			echo "check-error-log: status=failed reason=unknown_argument" >&2
			exit 1
			;;
	esac
done

EXCLUDES=("${DEFAULT_EXCLUDES[@]}" ${EXTRA_EXCLUDES[@]+"${EXTRA_EXCLUDES[@]}"})

if [ "$LIST_FILTERS" -eq 1 ]; then
	printf '%s\n' "${EXCLUDES[@]}"
	exit 0
fi

# Drop the noise lines. grep exits 1 when it filters everything out, which is a
# perfectly good "no errors today" result rather than a failure — so absorb it.
apply_filters() {
	local grep_args=()
	local pattern
	for pattern in "${EXCLUDES[@]}"; do
		grep_args+=(-e "$pattern")
	done
	grep -Fv "${grep_args[@]}" || true
}

if [ "$FILTER_STDIN" -eq 1 ]; then
	apply_filters
	exit 0
fi

# ── Credentials ───────────────────────────────────────────────────────────
ENV_FILE="$PROJECT_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
	echo "check-error-log: .env not found at $ENV_FILE" >&2
	echo "check-error-log: status=failed reason=missing_env_file" >&2
	exit 1
fi

set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

for var in DEPLOY_SSH_HOST DEPLOY_SSH_USER DEPLOY_SSH_PORT DEPLOY_REMOTE_WP_PATH; do
	if [ -z "${!var:-}" ]; then
		echo "check-error-log: $var not set in .env" >&2
		echo "check-error-log: status=failed reason=missing_env_var" >&2
		exit 1
	fi
done

# ── Remote collector ──────────────────────────────────────────────────────
# Runs on the server via `bash -s`. $1 = --since date (may be empty), $2 = the
# WordPress root. Writes the log lines to stdout and its diagnostics to stderr.
read -r -d '' REMOTE_SCRIPT <<'REMOTE' || true
set -u
since="$1"
wp_root="$2"

case "$wp_root" in
	"~/"*) wp_root="$HOME/${wp_root#\~/}" ;;
esac

cd "$wp_root/wp-content" 2>/dev/null || {
	echo "remote: wp-content not found under $wp_root" >&2
	exit 21
}

files=""
if [ -n "$since" ]; then
	# A rotated file carries the date of the rotation, not of its contents:
	# debug-2026-08-02.log holds the lines logged on 1 August. So "contents on
	# or after $since" means "rotation date strictly after $since". ISO dates
	# sort correctly as plain strings, so no date arithmetic is needed.
	for f in debug-*.log; do
		[ -e "$f" ] || continue
		d="${f#debug-}"
		d="${d%.log}"
		if [ "$d" \> "$since" ]; then
			files="$files $f"
		fi
	done
else
	yesterday="debug-$(date -u +%Y-%m-%d).log"
	[ -e "$yesterday" ] && files=" $yesterday"
fi

# debug.log is always today, and always goes last so the output stays in order.
[ -e debug.log ] && files="$files debug.log"

if [ -z "$files" ]; then
	echo "remote: no debug log files matched" >&2
	exit 22
fi

echo "remote: scanning$files" >&2
# shellcheck disable=SC2086
cat $files
REMOTE

remote_cmd="bash -s -- $(printf '%q' "$SINCE") $(printf '%q' "$DEPLOY_REMOTE_WP_PATH")"

RAW_LOG="$(mktemp)"
STDERR_LOG="$(mktemp)"
trap 'rm -f "$RAW_LOG" "$STDERR_LOG"' EXIT

# SiteGround drops SSH connections often enough that a single transient failure
# would otherwise be indistinguishable from a clean run. Three tries, backing off.
ssh_rc=0
for attempt in 1 2 3; do
	ssh_rc=0
	ssh -o BatchMode=yes -o ConnectTimeout=25 -o ServerAliveInterval=10 \
		-p "$DEPLOY_SSH_PORT" \
		"$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
		"$remote_cmd" \
		<<<"$REMOTE_SCRIPT" \
		>"$RAW_LOG" 2>"$STDERR_LOG" || ssh_rc=$?

	# 21/22 come from the remote script itself — retrying will not change them.
	if [ "$ssh_rc" -eq 0 ] || [ "$ssh_rc" -eq 21 ] || [ "$ssh_rc" -eq 22 ]; then
		break
	fi

	if [ "$attempt" -lt 3 ]; then
		echo "check-error-log: ssh attempt $attempt failed (rc=$ssh_rc), retrying" >&2
		sleep $((attempt * 5))
	fi
done

cat "$STDERR_LOG" >&2

if [ "$ssh_rc" -ne 0 ]; then
	case "$ssh_rc" in
		21) reason=remote_path_missing ;;
		22) reason=no_log_files ;;
		255) reason=ssh_unreachable ;;
		*) reason="remote_exit_$ssh_rc" ;;
	esac
	echo "check-error-log: status=failed reason=$reason" >&2
	exit 1
fi

FILTERED_LOG="$(mktemp)"
trap 'rm -f "$RAW_LOG" "$STDERR_LOG" "$FILTERED_LOG"' EXIT

apply_filters <"$RAW_LOG" >"$FILTERED_LOG"
cat "$FILTERED_LOG"

lines_in=$(wc -l <"$RAW_LOG" | tr -d ' ')
lines_out=$(wc -l <"$FILTERED_LOG" | tr -d ' ')
files_scanned=$(sed -n 's/^remote: scanning //p' "$STDERR_LOG" | tr ' ' '\n' | grep -c . || true)

echo "check-error-log: status=ok files=$files_scanned lines_in=$lines_in lines_out=$lines_out" >&2
