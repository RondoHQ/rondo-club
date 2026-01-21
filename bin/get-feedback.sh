#!/bin/bash
#
# Caelis Feedback Retrieval Script
# Retrieves a single feedback item from Caelis for Claude Code to process.
#
# Usage:
#   bin/get-feedback.sh                    # Get oldest new feedback item
#   bin/get-feedback.sh --status=new       # Filter by status
#   bin/get-feedback.sh --type=bug         # Filter by type (bug/feature_request)
#   bin/get-feedback.sh --id=123           # Get specific feedback item
#   bin/get-feedback.sh --help             # Show help
#
# Pipe to Claude Code:
#   bin/get-feedback.sh | claude
#

set -e

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Lock file to prevent concurrent runs
LOCK_FILE="/tmp/caelis-feedback-claude.lock"
LOCK_CREATED=false

# Check if another instance is already running
check_lock() {
    if [ -f "$LOCK_FILE" ]; then
        local pid=$(cat "$LOCK_FILE" 2>/dev/null)
        if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
            log "INFO" "Another session already running (PID: $pid), exiting"
            echo "Another feedback session is already running (PID: $pid)" >&2
            echo "Lock file: $LOCK_FILE" >&2
            exit 0
        else
            # Stale lock file, remove it
            log "INFO" "Removing stale lock file"
            rm -f "$LOCK_FILE"
        fi
    fi
}

# Create lock file with current PID
create_lock() {
    echo $$ > "$LOCK_FILE"
    LOCK_CREATED=true
}

# Remove lock file on exit (only if we created it)
cleanup() {
    if [ "$LOCK_CREATED" = true ]; then
        rm -f "$LOCK_FILE"
    fi
}
trap cleanup EXIT

# Colors for stderr output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Log file
LOG_FILE="$PROJECT_ROOT/logs/feedback-processor.log"
mkdir -p "$(dirname "$LOG_FILE")"

# Logging function
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

log "INFO" "=== Script started (PID: $$) ==="

# Load environment variables
ENV_FILE="$PROJECT_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}" >&2
    echo "Copy .env.example to .env and fill in your credentials." >&2
    exit 1
fi

# Source .env file (using source for proper variable expansion)
set -a
source "$ENV_FILE"
set +a

# Validate required variables
if [ -z "$CAELIS_API_URL" ] || [ -z "$CAELIS_API_USER" ] || [ -z "$CAELIS_API_PASSWORD" ]; then
    echo -e "${RED}Error: API credentials not configured in .env${NC}" >&2
    echo "Required variables: CAELIS_API_URL, CAELIS_API_USER, CAELIS_API_PASSWORD" >&2
    echo "" >&2
    echo "To set up:" >&2
    echo "1. Go to WordPress Admin > Users > Your Profile" >&2
    echo "2. Scroll to 'Application Passwords'" >&2
    echo "3. Create a new application password named 'Caelis CLI'" >&2
    echo "4. Add to .env:" >&2
    echo "   CAELIS_API_URL=https://your-site.com" >&2
    echo "   CAELIS_API_USER=your-username" >&2
    echo "   CAELIS_API_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx" >&2
    exit 1
fi

# Check for jq
if ! command -v jq &> /dev/null; then
    echo -e "${RED}Error: jq is required but not installed${NC}" >&2
    echo "Install with: brew install jq" >&2
    exit 1
fi

# Default values
STATUS="new"
TYPE=""
FEEDBACK_ID=""
OUTPUT_FORMAT="claude"
RUN_CLAUDE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --status=*)
            STATUS="${1#*=}"
            shift
            ;;
        --type=*)
            TYPE="${1#*=}"
            shift
            ;;
        --id=*)
            FEEDBACK_ID="${1#*=}"
            shift
            ;;
        --all)
            STATUS=""
            shift
            ;;
        --run)
            RUN_CLAUDE=true
            shift
            ;;
        --json)
            OUTPUT_FORMAT="json"
            shift
            ;;
        --help|-h)
            echo "Caelis Feedback Retrieval Script"
            echo ""
            echo "Retrieves a single feedback item (oldest first) for Claude Code to process."
            echo ""
            echo "Usage: bin/get-feedback.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --run              Pipe output directly to Claude Code (use for cron)"
            echo "  --status=STATUS    Filter by status: new, in_progress, resolved, declined"
            echo "                     (default: new)"
            echo "  --type=TYPE        Filter by type: bug, feature_request"
            echo "  --id=ID            Get specific feedback item by ID"
            echo "  --all              Get from all statuses (oldest first)"
            echo "  --json             Output raw JSON instead of Claude-formatted text"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "Examples:"
            echo "  bin/get-feedback.sh --run                # Fetch and run Claude (for cron)"
            echo "  bin/get-feedback.sh                      # Get oldest new feedback"
            echo "  bin/get-feedback.sh --type=bug           # Get oldest new bug"
            echo "  bin/get-feedback.sh | claude             # Pipe to Claude Code"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}" >&2
            echo "Use --help for usage information." >&2
            exit 1
            ;;
    esac
done

# Only check/create lock when running Claude (for cron safety)
if [ "$RUN_CLAUDE" = true ]; then
    check_lock
    create_lock
fi

# Build API URL - fetch 1 item, oldest first
API_BASE="${CAELIS_API_URL}/wp-json/prm/v1/feedback"

if [ -n "$FEEDBACK_ID" ]; then
    API_URL="${API_BASE}/${FEEDBACK_ID}"
else
    API_URL="${API_BASE}?per_page=1&orderby=date&order=asc"
    [ -n "$STATUS" ] && API_URL="${API_URL}&status=${STATUS}"
    [ -n "$TYPE" ] && API_URL="${API_URL}&type=${TYPE}"
fi

# Fetch feedback
log "INFO" "Fetching feedback from API (status=$STATUS, type=$TYPE)"
echo -e "${YELLOW}Fetching feedback from Caelis...${NC}" >&2

RESPONSE=$(curl -s -w "\n%{http_code}" \
    -u "${CAELIS_API_USER}:${CAELIS_API_PASSWORD}" \
    -H "Accept: application/json" \
    "$API_URL")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" != "200" ]; then
    log "ERROR" "API request failed (HTTP $HTTP_CODE)"
    echo -e "${RED}Error: API request failed (HTTP $HTTP_CODE)${NC}" >&2
    echo "$BODY" >&2
    exit 1
fi

# Check if we got any results
if [ -n "$FEEDBACK_ID" ]; then
    ITEM_COUNT=1
else
    ITEM_COUNT=$(echo "$BODY" | jq 'length')
fi

if [ "$ITEM_COUNT" = "0" ] || [ "$ITEM_COUNT" = "null" ]; then
    log "INFO" "No feedback items found matching criteria"
    echo -e "${GREEN}No feedback items found matching your criteria.${NC}" >&2
    exit 0
fi

# Get the feedback ID for display
if [ -n "$FEEDBACK_ID" ]; then
    DISPLAY_ID="$FEEDBACK_ID"
else
    DISPLAY_ID=$(echo "$BODY" | jq -r '.[0].id')
fi
DISPLAY_TITLE=$(echo "$BODY" | jq -r 'if type == "array" then .[0].title else .title end')
log "INFO" "Retrieved feedback #${DISPLAY_ID}: ${DISPLAY_TITLE}"
echo -e "${GREEN}Retrieved feedback #${DISPLAY_ID}${NC}" >&2

# Output based on format
if [ "$OUTPUT_FORMAT" = "json" ]; then
    echo "$BODY" | jq .
    exit 0
fi

# Format for Claude Code
format_feedback_for_claude() {
    local json="$1"
    local is_single="$2"

    # Extract single item from array if needed
    if [ "$is_single" = "false" ]; then
        json=$(echo "$json" | jq '.[0]')
    fi

    local feedback_type=$(echo "$json" | jq -r '.meta.feedback_type')
    local id=$(echo "$json" | jq -r '.id')
    local title=$(echo "$json" | jq -r '.title')

    if [ "$feedback_type" = "bug" ]; then
        echo "# Bug Report #${id}: ${title}"
    else
        echo "# Feature Request #${id}: ${title}"
    fi

    echo ""
    echo "**Status:** $(echo "$json" | jq -r '.meta.status') | **Priority:** $(echo "$json" | jq -r '.meta.priority') | **Submitted:** $(echo "$json" | jq -r '.date')"
    echo "**By:** $(echo "$json" | jq -r '.author.name')"
    echo ""

    echo "## Description"
    echo "$json" | jq -r '.content'
    echo ""

    if [ "$feedback_type" = "bug" ]; then
        local steps=$(echo "$json" | jq -r '.meta.steps_to_reproduce // empty')
        local expected=$(echo "$json" | jq -r '.meta.expected_behavior // empty')
        local actual=$(echo "$json" | jq -r '.meta.actual_behavior // empty')

        [ -n "$steps" ] && echo "## Steps to Reproduce" && echo "$steps" && echo ""
        [ -n "$expected" ] && echo "## Expected Behavior" && echo "$expected" && echo ""
        [ -n "$actual" ] && echo "## Actual Behavior" && echo "$actual" && echo ""
    else
        local use_case=$(echo "$json" | jq -r '.meta.use_case // empty')
        [ -n "$use_case" ] && echo "## Use Case" && echo "$use_case" && echo ""
    fi

    # Context info
    local url_context=$(echo "$json" | jq -r '.meta.url_context // empty')
    local browser=$(echo "$json" | jq -r '.meta.browser_info // empty')
    local version=$(echo "$json" | jq -r '.meta.app_version // empty')

    if [ -n "$url_context" ] || [ -n "$browser" ] || [ -n "$version" ]; then
        echo "## Context"
        [ -n "$url_context" ] && echo "- **URL:** $url_context"
        [ -n "$browser" ] && echo "- **Browser:** $browser"
        [ -n "$version" ] && echo "- **App Version:** $version"
        echo ""
    fi

    echo "---"
    echo ""
    echo "Please analyze this feedback and help address it in the Caelis codebase."
    echo ""
    echo "IMPORTANT: When you're done, end your response with one of these status lines:"
    echo "- STATUS: RESOLVED - if you fixed the issue and deployed"
    echo "- STATUS: IN_PROGRESS - if you made progress but more work is needed"
    echo "- STATUS: NEEDS_INFO - if you need more information to proceed"
    echo "- STATUS: DECLINED - if this isn't something that should be fixed"
}

# Update feedback status via API
update_feedback_status() {
    local feedback_id="$1"
    local new_status="$2"

    log "INFO" "Updating feedback #${feedback_id} status to: ${new_status}"
    echo -e "${YELLOW}Updating feedback #${feedback_id} status to: ${new_status}${NC}" >&2

    local response=$(curl -s -w "\n%{http_code}" \
        -X PUT \
        -u "${CAELIS_API_USER}:${CAELIS_API_PASSWORD}" \
        -H "Content-Type: application/json" \
        -d "{\"status\": \"${new_status}\"}" \
        "${CAELIS_API_URL}/wp-json/prm/v1/feedback/${feedback_id}")

    local http_code=$(echo "$response" | tail -n1)

    if [ "$http_code" = "200" ]; then
        log "INFO" "Feedback #${feedback_id} status updated to ${new_status}"
        echo -e "${GREEN}Feedback status updated successfully.${NC}" >&2
        return 0
    else
        log "ERROR" "Failed to update feedback #${feedback_id} status (HTTP $http_code)"
        echo -e "${RED}Failed to update feedback status (HTTP $http_code)${NC}" >&2
        return 1
    fi
}

# Parse Claude's response for status
parse_claude_status() {
    local response="$1"

    # Look for STATUS: lines (case insensitive)
    if echo "$response" | grep -qi "STATUS:.*RESOLVED"; then
        echo "resolved"
    elif echo "$response" | grep -qi "STATUS:.*IN_PROGRESS"; then
        echo "in_progress"
    elif echo "$response" | grep -qi "STATUS:.*DECLINED"; then
        echo "declined"
    elif echo "$response" | grep -qi "STATUS:.*NEEDS_INFO"; then
        echo "needs_info"
    else
        # Fallback: check for deployment indicators
        if echo "$response" | grep -qi "deployed\|deployment complete"; then
            echo "resolved"
        else
            echo ""
        fi
    fi
}

# Generate the formatted output
if [ -n "$FEEDBACK_ID" ]; then
    OUTPUT=$(format_feedback_for_claude "$BODY" "true")
    CURRENT_FEEDBACK_ID="$FEEDBACK_ID"
else
    OUTPUT=$(format_feedback_for_claude "$BODY" "false")
    CURRENT_FEEDBACK_ID="$DISPLAY_ID"
fi

# Either output to stdout or run Claude
if [ "$RUN_CLAUDE" = true ]; then
    log "INFO" "Starting Claude Code session for feedback #${CURRENT_FEEDBACK_ID}"
    echo -e "${YELLOW}Starting Claude Code session...${NC}" >&2

    # Run Claude and capture output
    # --dangerously-skip-permissions allows autonomous operation without prompts
    # --print outputs response and exits (for piped input)
    CLAUDE_OUTPUT=$(echo "$OUTPUT" | claude --print --dangerously-skip-permissions 2>&1)
    CLAUDE_EXIT=$?

    # Display Claude's output
    echo "$CLAUDE_OUTPUT"

    if [ $CLAUDE_EXIT -eq 0 ]; then
        log "INFO" "Claude session completed successfully"
        echo -e "${GREEN}Claude session completed.${NC}" >&2

        # Parse status from Claude's response
        NEW_STATUS=$(parse_claude_status "$CLAUDE_OUTPUT")

        if [ -n "$NEW_STATUS" ] && [ "$NEW_STATUS" != "needs_info" ]; then
            update_feedback_status "$CURRENT_FEEDBACK_ID" "$NEW_STATUS"
        elif [ "$NEW_STATUS" = "needs_info" ]; then
            log "INFO" "Claude needs more information, status not changed"
            echo -e "${YELLOW}Claude needs more information. Status not changed.${NC}" >&2
        else
            log "WARN" "No status indicator found in Claude's response"
            echo -e "${YELLOW}No status indicator found in Claude's response.${NC}" >&2
        fi
    else
        log "ERROR" "Claude session failed (exit code: $CLAUDE_EXIT)"
        echo -e "${RED}Claude session failed (exit code: $CLAUDE_EXIT)${NC}" >&2
    fi

    log "INFO" "=== Script finished ==="
else
    echo "$OUTPUT"
fi
