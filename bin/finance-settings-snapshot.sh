#!/bin/bash
#
# Rondo Club Finance Settings Snapshot Tool
# Dumps all 48 finance-surface WordPress options (40 rondo_finance_* + 8
# rondo_membership_pass_*) plus the full /rondo/v1/finance-settings REST
# response to a local JSON file. Used for regression testing during the
# v34.0 Finance Service Decomposition milestone.
#
# The PHP payload at bin/finance-settings-snapshot.php runs via
# `wp eval-file -` over SSH; it is read-only (no writes, no emails, no
# side effects).
#
# Usage:
#   bin/finance-settings-snapshot.sh                                      # default output: finance-settings-snapshot.json
#   bin/finance-settings-snapshot.sh --output .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json
#   bin/finance-settings-snapshot.sh --site demo                          # run against demo instead of prod
#
# Requires DEPLOY_SSH_* entries in .env (same ones used by bin/deploy.sh).
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
PHP_PAYLOAD="$SCRIPT_DIR/finance-settings-snapshot.php"

OUTPUT="$PROJECT_ROOT/finance-settings-snapshot.json"
SITE="prod"

usage() {
	cat <<'EOF'
Rondo Club Finance Settings Snapshot Tool

Runs bin/finance-settings-snapshot.php on the target WordPress install via
SSH + wp eval-file and saves the resulting JSON locally. Read-only: no
writes, no emails. Used as a regression harness for the v34.0 Finance
Service Decomposition milestone.

Options:
  --output PATH   Write snapshot to PATH (default: finance-settings-snapshot.json)
  --site NAME     Target site: prod (default) or demo
  --help, -h      Show this help

Environment (.env):
  DEPLOY_SSH_HOST, DEPLOY_SSH_PORT, DEPLOY_SSH_USER, DEPLOY_REMOTE_WP_PATH
  (same vars used by bin/deploy.sh)

Examples:
  bin/finance-settings-snapshot.sh --output .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json
  bin/finance-settings-snapshot.sh --output post-phase-220.json
EOF
}

while [ $# -gt 0 ]; do
	case "$1" in
		--output)
			OUTPUT="${2:-}"
			shift 2
			;;
		--site)
			SITE="${2:-}"
			shift 2
			;;
		--help|-h)
			usage
			exit 0
			;;
		*)
			echo "Error: unknown option: $1" >&2
			usage
			exit 1
			;;
	esac
done

if [ ! -f "$PHP_PAYLOAD" ]; then
	echo "Error: PHP payload not found at $PHP_PAYLOAD" >&2
	exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
	echo "Error: .env file not found at $ENV_FILE" >&2
	exit 1
fi

set -a
set +u
# shellcheck disable=SC1090
source "$ENV_FILE"
set -u
set +a

case "$SITE" in
	prod)
		SSH_USER="${DEPLOY_SSH_USER:-}"
		WP_PATH="${DEPLOY_REMOTE_WP_PATH:-}"
		;;
	demo)
		SSH_USER="${DEPLOY_DEMO_SSH_USER:-${DEPLOY_SSH_USER:-}}"
		WP_PATH="${DEPLOY_DEMO_REMOTE_WP_PATH:-${DEPLOY_REMOTE_WP_PATH:-}}"
		;;
	*)
		echo "Error: unknown --site '$SITE' (expected prod or demo)" >&2
		exit 1
		;;
esac

SSH_HOST="${DEPLOY_SSH_HOST:-}"
SSH_PORT="${DEPLOY_SSH_PORT:-22}"

if [ -z "$SSH_HOST" ] || [ -z "$SSH_USER" ] || [ -z "$WP_PATH" ]; then
	echo "Error: missing SSH config for site '$SITE' (need DEPLOY_SSH_HOST/USER/PATH in .env)" >&2
	exit 1
fi

OUTPUT_DIR="$(dirname "$OUTPUT")"
mkdir -p "$OUTPUT_DIR"

echo "Running finance settings snapshot against $SITE ($SSH_USER@$SSH_HOST:$SSH_PORT)..."

# wp eval-file - reads PHP from stdin; pipe the payload in.
ssh -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" \
	"cd $WP_PATH && wp eval-file - 2>/dev/null" \
	< "$PHP_PAYLOAD" \
	> "$OUTPUT"

if [ ! -s "$OUTPUT" ]; then
	echo "Error: snapshot output is empty at $OUTPUT" >&2
	exit 1
fi

# Sanity check: must be valid JSON with schema_version, options object,
# rest_response object, and option_count == 48.
if ! jq -e '.schema_version == 1 and (.options | type == "object") and (.rest_response | type == "object") and (.option_count == 48)' "$OUTPUT" >/dev/null 2>&1; then
	echo "Error: snapshot output does not look like valid finance-settings-snapshot JSON:" >&2
	head -c 500 "$OUTPUT" >&2
	echo >&2
	exit 1
fi

OPTION_COUNT=$(jq -r '.option_count' "$OUTPUT")
SITE_URL=$(jq -r '.site_url' "$OUTPUT")

echo "Snapshot saved: $OUTPUT"
echo "  Site URL:     $SITE_URL"
echo "  Option count: $OPTION_COUNT / 48 expected"
echo "  Target:       $SITE"
