#!/bin/bash
#
# Rondo Club Remote Person Values Utility
# Retrieves and updates person values over the REST API (remote-safe, no local WP-CLI required).
#
# Usage:
#   bin/person-values.sh find --query "jan de vries"
#   bin/person-values.sh get --id 123 --field first_name
#   bin/person-values.sh set --id 123 --field first_name --value "Jan" --type string
#   bin/person-values.sh dump --id 123
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"

if [ -f "$ENV_FILE" ]; then
	set -a
	source "$ENV_FILE"
	set +a
fi

API_URL="${RONDO_API_URL:-}"
API_USER="${RONDO_API_USER:-}"
API_PASSWORD="${RONDO_API_PASSWORD:-}"

RESP_STATUS=""
RESP_BODY=""

usage() {
	cat <<'EOF'
Rondo Club Remote Person Values Utility

Uses the remote WordPress REST API (application password auth).

Commands:
  find    Search people by text query
  get     Get one field value from a person
  set     Update one field value on a person
  dump    Fetch full person payload
  help    Show this help

Global auth options (override .env):
  --url URL
  --user USERNAME
  --password APP_PASSWORD

find options:
  --query TEXT            Required search query
  --per-page N            Results per page (default: 20, max: 100)
  --json                  Output raw JSON

get options:
  --id ID                 Required person ID
  --field KEY             Required field key (example: first_name)
  --json                  Output JSON object instead of plain value

set options:
  --id ID                 Required person ID
  --field KEY             Required field key
  --value VALUE           Required value
  --type TYPE             string|number|boolean|null|json (default: string)
  --dry-run               Print payload(s) without updating
  --json                  Output JSON object

dump options:
  --id ID                 Required person ID

Examples:
  bin/person-values.sh find --query "joost"
  bin/person-values.sh get --id 123 --field last_name
  bin/person-values.sh set --id 123 --field former_member --value true --type boolean
  bin/person-values.sh set --id 123 --field contact_info --type json --value '[{"contact_type":"email","contact_label":"Werk","contact_value":"x@example.com"}]'
EOF
}

require_cmds() {
	command -v curl >/dev/null 2>&1 || {
		echo "Error: curl is required." >&2
		exit 1
	}
	command -v jq >/dev/null 2>&1 || {
		echo "Error: jq is required." >&2
		exit 1
	}
}

require_auth() {
	if [ -z "$API_URL" ] || [ -z "$API_USER" ] || [ -z "$API_PASSWORD" ]; then
		echo "Error: Missing API credentials. Set RONDO_API_URL, RONDO_API_USER, and RONDO_API_PASSWORD in .env or pass --url/--user/--password." >&2
		exit 1
	fi
}

encode_uri() {
	jq -rn --arg v "$1" '$v|@uri'
}

api_request() {
	local method="$1"
	local endpoint="$2"
	local data="${3:-}"
	local url="${API_URL%/}/wp-json${endpoint}"
	local tmp_file
	tmp_file="$(mktemp)"

	if [ -n "$data" ]; then
		if ! RESP_STATUS="$(curl -sS -o "$tmp_file" -w "%{http_code}" -u "$API_USER:$API_PASSWORD" -H "Content-Type: application/json" -X "$method" --data "$data" "$url")"; then
			rm -f "$tmp_file"
			echo "Error: API request failed for $method $endpoint" >&2
			exit 1
		fi
	else
		if ! RESP_STATUS="$(curl -sS -o "$tmp_file" -w "%{http_code}" -u "$API_USER:$API_PASSWORD" -X "$method" "$url")"; then
			rm -f "$tmp_file"
			echo "Error: API request failed for $method $endpoint" >&2
			exit 1
		fi
	fi

	RESP_BODY="$(cat "$tmp_file")"
	rm -f "$tmp_file"
}

api_error_message() {
	echo "$RESP_BODY" | jq -r '.message // empty' 2>/dev/null
}

ensure_ok() {
	if [[ "$RESP_STATUS" != "200" && "$RESP_STATUS" != "201" ]]; then
		local message
		message="$(api_error_message)"
		if [ -n "$message" ]; then
			echo "Error ($RESP_STATUS): $message" >&2
		else
			echo "Error ($RESP_STATUS): API request failed." >&2
			echo "$RESP_BODY" >&2
		fi
		exit 1
	fi
}

json_value_for_type() {
	local value="$1"
	local type="$2"

	case "$type" in
		string)
			jq -cn --arg v "$value" '$v'
			;;
		number)
			if ! jq -cn --arg v "$value" '($v|tonumber)' >/dev/null 2>&1; then
				echo "Error: --type number requires a numeric --value." >&2
				exit 1
			fi
			jq -cn --arg v "$value" '($v|tonumber)'
			;;
		boolean)
			if [ "$value" != "true" ] && [ "$value" != "false" ]; then
				echo "Error: --type boolean requires --value true or false." >&2
				exit 1
			fi
			jq -cn --arg v "$value" '($v == "true")'
			;;
		null)
			echo "null"
			;;
		json)
			if ! echo "$value" | jq -ce '.' >/dev/null 2>&1; then
				echo "Error: --type json requires valid JSON in --value." >&2
				exit 1
			fi
			echo "$value" | jq -c '.'
			;;
		*)
			echo "Error: Invalid --type '$type'. Use string, number, boolean, null, or json." >&2
			exit 1
			;;
	esac
}

resolve_get_value() {
	local field="$1"

	echo "$RESP_BODY" | jq -c --arg key "$field" '
		if (.fields | type == "object") and (.fields | has($key)) then
			{source: "fields", value: .fields[$key]}
		else
			null
		end
	'
}

run_find() {
	local query=""
	local per_page="20"
	local json_output="false"

	while [ $# -gt 0 ]; do
		case "$1" in
			--query)
				query="${2:-}"
				shift 2
				;;
			--per-page)
				per_page="${2:-}"
				shift 2
				;;
			--json)
				json_output="true"
				shift
				;;
			--url)
				API_URL="${2:-}"
				shift 2
				;;
			--user)
				API_USER="${2:-}"
				shift 2
				;;
			--password)
				API_PASSWORD="${2:-}"
				shift 2
				;;
			--help|-h)
				usage
				exit 0
				;;
			*)
				echo "Error: Unknown option for find: $1" >&2
				exit 1
				;;
		esac
	done

	if [ -z "$query" ]; then
		echo "Error: --query is required for find." >&2
		exit 1
	fi

	if ! [[ "$per_page" =~ ^[0-9]+$ ]] || [ "$per_page" -lt 1 ] || [ "$per_page" -gt 100 ]; then
		echo "Error: --per-page must be a number between 1 and 100." >&2
		exit 1
	fi

	require_auth

	local encoded_query
	encoded_query="$(encode_uri "$query")"

	api_request "GET" "/wp/v2/people?search=${encoded_query}&per_page=${per_page}&_fields=id,title,slug"
	ensure_ok

	if [ "$json_output" = "true" ]; then
		echo "$RESP_BODY" | jq '.'
	else
		echo "ID\tTitle\tSlug"
		echo "$RESP_BODY" | jq -r '.[] | "\(.id)\t\(.title.rendered // "")\t\(.slug // "")"'
	fi
}

run_get() {
	local id=""
	local field=""
	local json_output="false"

	while [ $# -gt 0 ]; do
		case "$1" in
			--id)
				id="${2:-}"
				shift 2
				;;
			--field)
				field="${2:-}"
				shift 2
				;;
			--json)
				json_output="true"
				shift
				;;
			--url)
				API_URL="${2:-}"
				shift 2
				;;
			--user)
				API_USER="${2:-}"
				shift 2
				;;
			--password)
				API_PASSWORD="${2:-}"
				shift 2
				;;
			--help|-h)
				usage
				exit 0
				;;
			*)
				echo "Error: Unknown option for get: $1" >&2
				exit 1
				;;
		esac
	done

	if [ -z "$id" ] || [ -z "$field" ]; then
		echo "Error: --id and --field are required for get." >&2
		exit 1
	fi
	if ! [[ "$id" =~ ^[0-9]+$ ]]; then
		echo "Error: --id must be numeric." >&2
		exit 1
	fi

	require_auth

	api_request "GET" "/wp/v2/people/${id}?context=edit"
	ensure_ok

	local resolved
	resolved="$(resolve_get_value "$field")"

	if [ "$resolved" = "null" ] || [ -z "$resolved" ]; then
		echo "Error: Canonical field '$field' not found for person $id." >&2
		exit 1
	fi

	if [ "$json_output" = "true" ]; then
		echo "$resolved" | jq --arg field "$field" --argjson id "$id" '. + {id: $id, field: $field}'
	else
		echo "$resolved" | jq -r '.value | if type == "string" then . else @json end'
	fi
}

run_set() {
	local id=""
	local field=""
	local value=""
	local has_value="false"
	local value_type="string"
	local json_output="false"
	local dry_run="false"

	while [ $# -gt 0 ]; do
		case "$1" in
			--id)
				id="${2:-}"
				shift 2
				;;
			--field)
				field="${2:-}"
				shift 2
				;;
			--value)
				value="${2:-}"
				has_value="true"
				shift 2
				;;
			--type)
				value_type="${2:-}"
				shift 2
				;;
			--dry-run)
				dry_run="true"
				shift
				;;
			--json)
				json_output="true"
				shift
				;;
			--url)
				API_URL="${2:-}"
				shift 2
				;;
			--user)
				API_USER="${2:-}"
				shift 2
				;;
			--password)
				API_PASSWORD="${2:-}"
				shift 2
				;;
			--help|-h)
				usage
				exit 0
				;;
			*)
				echo "Error: Unknown option for set: $1" >&2
				exit 1
				;;
		esac
	done

	if [ -z "$id" ] || [ -z "$field" ] || [ "$has_value" != "true" ]; then
		echo "Error: --id, --field, and --value are required for set." >&2
		exit 1
	fi
	if ! [[ "$id" =~ ^[0-9]+$ ]]; then
		echo "Error: --id must be numeric." >&2
		exit 1
	fi

	require_auth

	local value_json
	value_json="$(json_value_for_type "$value" "$value_type")"

	local payload
	payload="$(jq -cn --arg key "$field" --argjson value "$value_json" '{fields: {($key): $value}}')"

	if [ "$dry_run" = "true" ]; then
		echo "$payload" | jq '.'
		exit 0
	fi

	api_request "POST" "/wp/v2/people/${id}" "$payload"

	if [[ "$RESP_STATUS" != "200" && "$RESP_STATUS" != "201" ]]; then
		echo "Error: Could not update field '$field' for person $id. HTTP $RESP_STATUS: $(api_error_message)" >&2
		exit 1
	fi

	local resolved
	resolved="$(resolve_get_value "$field")"
	if [ "$resolved" = "null" ] || [ -z "$resolved" ]; then
		resolved="$(jq -cn --argjson val "$value_json" '{source: "fields", value: $val}')"
	fi

	if [ "$json_output" = "true" ]; then
		echo "$resolved" | jq --arg field "$field" --argjson id "$id" '. + {id: $id, field: $field}'
	else
		echo "Updated person ${id} canonical field '${field}'."
		echo "$resolved" | jq -r '.value | if type == "string" then . else @json end'
	fi
}

run_dump() {
	local id=""

	while [ $# -gt 0 ]; do
		case "$1" in
			--id)
				id="${2:-}"
				shift 2
				;;
			--url)
				API_URL="${2:-}"
				shift 2
				;;
			--user)
				API_USER="${2:-}"
				shift 2
				;;
			--password)
				API_PASSWORD="${2:-}"
				shift 2
				;;
			--help|-h)
				usage
				exit 0
				;;
			*)
				echo "Error: Unknown option for dump: $1" >&2
				exit 1
				;;
		esac
	done

	if [ -z "$id" ]; then
		echo "Error: --id is required for dump." >&2
		exit 1
	fi
	if ! [[ "$id" =~ ^[0-9]+$ ]]; then
		echo "Error: --id must be numeric." >&2
		exit 1
	fi

	require_auth

	api_request "GET" "/wp/v2/people/${id}?context=edit"
	ensure_ok
	echo "$RESP_BODY" | jq '.'
}

main() {
	require_cmds

	local cmd="${1:-help}"
	shift || true

	case "$cmd" in
		find)
			run_find "$@"
			;;
		get)
			run_get "$@"
			;;
		set)
			run_set "$@"
			;;
		dump)
			run_dump "$@"
			;;
		help|--help|-h)
			usage
			;;
		*)
			echo "Error: Unknown command '$cmd'." >&2
			echo "" >&2
			usage
			exit 1
			;;
	esac
}

main "$@"
