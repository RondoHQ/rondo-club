#!/bin/bash
#
# Rondo Club Feedback Agent Script
# Retrieves feedback items from Rondo Club and processes them with Claude Code.
# Creates PRs for resolved items, posts follow-up questions for unclear items.
#
# Usage:
#   bin/get-feedback.sh                    # Get oldest approved feedback item
#   bin/get-feedback.sh --run              # Fetch and process with Claude Code
#   bin/get-feedback.sh --loop             # Process all items then optionally optimize
#   bin/get-feedback.sh --loop --optimize  # Process all items, review PRs, then optimize
#   bin/get-feedback.sh --status=new       # Filter by status
#   bin/get-feedback.sh --type=bug         # Filter by type (bug/feature_request)
#   bin/get-feedback.sh --id=123           # Get specific feedback item
#   bin/get-feedback.sh --json             # Output raw JSON
#   bin/get-feedback.sh --help             # Show help
#

# Wrap entire script in a block so bash reads the whole file before executing.
# This prevents corruption if git pull updates this script while it's running.
{

# Don't use set -e - we want to handle errors ourselves and log them

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Lock file to prevent concurrent runs
LOCK_FILE="/tmp/rondo-feedback-claude.lock"
LOCK_CREATED=false
SCRIPT_COMPLETED=false

# Track current feedback item for crash cleanup
CURRENT_FEEDBACK_ID=""
ORIGINAL_STATUS=""

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

# Remove lock file on exit, reset feedback status if crashed, and clean up git
cleanup() {
    local exit_code=$?
    if [ "$LOCK_CREATED" = true ]; then
        if [ "$SCRIPT_COMPLETED" = false ]; then
            local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
            echo "[$timestamp] [ERROR] Script exited unexpectedly (exit code: $exit_code, signal: $TRAPPED_SIGNAL)" >> "$PROJECT_ROOT/logs/feedback-processor.log"

            # Reset feedback status back to approved if we crashed mid-processing
            if [ -n "$CURRENT_FEEDBACK_ID" ] && [ -n "$ORIGINAL_STATUS" ]; then
                echo "[$timestamp] [ERROR] Resetting feedback #${CURRENT_FEEDBACK_ID} status to ${ORIGINAL_STATUS}" >> "$PROJECT_ROOT/logs/feedback-processor.log"
                curl -s -X PUT \
                    -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
                    -H "Content-Type: application/json" \
                    -d "{\"status\": \"${ORIGINAL_STATUS}\"}" \
                    "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${CURRENT_FEEDBACK_ID}" > /dev/null 2>&1
            fi
        fi

        # Always return to main and clean up branches
        cd "$PROJECT_ROOT" 2>/dev/null
        git checkout main 2>/dev/null
        git branch --merged main | grep -E '^\s+(feedback|optimize)/' | xargs -r git branch -d 2>/dev/null

        rm -f "$LOCK_FILE"
    fi
}

# Handle termination signals
handle_signal() {
    TRAPPED_SIGNAL="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [ERROR] Script received signal: $TRAPPED_SIGNAL" >> "$PROJECT_ROOT/logs/feedback-processor.log"
    exit 1
}

TRAPPED_SIGNAL=""
trap cleanup EXIT
trap 'handle_signal SIGTERM' SIGTERM
trap 'handle_signal SIGINT' SIGINT
trap 'handle_signal SIGHUP' SIGHUP

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


# Add HOMEBREW_PATH to PATH if set (needed for cron/launchd which don't have full PATH)
if [ -n "$HOMEBREW_PATH" ]; then
    export PATH="$HOMEBREW_PATH:$PATH"
fi

# Ensure HOME is set (needed for Claude to find its config at ~/.claude/)
if [ -n "$USER_HOME" ]; then
    export HOME="$USER_HOME"
fi


# Validate required variables
if [ -z "$RONDO_API_URL" ] || [ -z "$RONDO_API_USER" ] || [ -z "$RONDO_API_PASSWORD" ]; then
    echo -e "${RED}Error: API credentials not configured in .env${NC}" >&2
    echo "Required variables: RONDO_API_URL, RONDO_API_USER, RONDO_API_PASSWORD" >&2
    exit 1
fi

# Check for jq
if ! command -v jq &> /dev/null; then
    echo -e "${RED}Error: jq is required but not installed${NC}" >&2
    echo "Install with: brew install jq" >&2
    exit 1
fi

# Default values
STATUS="approved"
TYPE=""
FEEDBACK_ID=""
OUTPUT_FORMAT="claude"
RUN_CLAUDE=false
LOOP_MODE=false
OPTIMIZE_MODE=false

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
        --loop)
            LOOP_MODE=true
            RUN_CLAUDE=true  # Loop mode requires running Claude
            shift
            ;;
        --optimize)
            OPTIMIZE_MODE=true
            shift
            ;;
        --json)
            OUTPUT_FORMAT="json"
            shift
            ;;
        --help|-h)
            echo "Rondo Club Feedback Agent Script"
            echo ""
            echo "Retrieves feedback items and processes them with Claude Code."
            echo "Creates PRs for resolved items, posts follow-up questions when needed."
            echo ""
            echo "Usage: bin/get-feedback.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --run              Pipe output directly to Claude Code"
            echo "  --loop             Process all feedback items one by one (implies --run)"
            echo "  --optimize         When no feedback items, review code for optimization PRs"
            echo "  --status=STATUS    Filter by status (default: approved)"
            echo "  --type=TYPE        Filter by type: bug, feature_request"
            echo "  --id=ID            Get specific feedback item by ID"
            echo "  --all              Get from all statuses"
            echo "  --json             Output raw JSON"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "Examples:"
            echo "  bin/get-feedback.sh --run                    # Process one feedback item"
            echo "  bin/get-feedback.sh --loop --optimize        # Process all, then optimize"
            echo "  bin/get-feedback.sh                          # Get oldest approved feedback"
            echo "  bin/get-feedback.sh | claude                 # Pipe to Claude Code"
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

# Ensure we're on a clean main branch before starting
# Works in the current directory (caller should cd first)
ensure_clean_main() {
    # Check for dirty working directory
    if [ -n "$(git status --porcelain)" ]; then
        log "ERROR" "Working directory is dirty in $(pwd), aborting"
        echo -e "${RED}Error: Working directory is dirty in $(pwd). Commit or stash changes first.${NC}" >&2
        return 1
    fi

    git checkout main 2>/dev/null
    git pull --ff-only 2>/dev/null

    return 0
}

# Update feedback status via API
update_feedback_status() {
    local feedback_id="$1"
    local new_status="$2"

    log "INFO" "Updating feedback #${feedback_id} status to: ${new_status}"
    echo -e "${YELLOW}Updating feedback #${feedback_id} status to: ${new_status}${NC}" >&2

    local response=$(curl -s -w "\n%{http_code}" \
        -X PUT \
        -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
        -H "Content-Type: application/json" \
        -d "{\"status\": \"${new_status}\"}" \
        "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${feedback_id}")

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

# Update feedback meta (pr_url, agent_branch)
update_feedback_meta() {
    local feedback_id="$1"
    local pr_url="$2"
    local branch="$3"

    local meta_desc="pr_url=${pr_url}"
    [ -n "$branch" ] && meta_desc="${meta_desc}, branch=${branch}"
    log "INFO" "Updating feedback #${feedback_id} meta: ${meta_desc}"

    local json_data="{}"
    [ -n "$pr_url" ] && json_data=$(echo "$json_data" | jq --arg url "$pr_url" '. + {pr_url: $url}')
    [ -n "$branch" ] && json_data=$(echo "$json_data" | jq --arg b "$branch" '. + {agent_branch: $b}')

    curl -s -X PUT \
        -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
        -H "Content-Type: application/json" \
        -d "$json_data" \
        "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${feedback_id}" > /dev/null 2>&1
}

# Run Claude with a timeout (default 10 minutes)
# Usage: run_claude <prompt_file> <output_file> [timeout_seconds]
# Sets CLAUDE_EXIT and CLAUDE_OUTPUT globals
run_claude() {
    local prompt_file="$1"
    local output_file="$2"
    local timeout_secs="${3:-600}"

    CLAUDE_BIN="${CLAUDE_PATH:-claude}"

    "$CLAUDE_BIN" --print --dangerously-skip-permissions < "$prompt_file" > "$output_file" 2>&1 &
    local claude_pid=$!
    local elapsed=0

    while kill -0 "$claude_pid" 2>/dev/null; do
        sleep 10
        elapsed=$((elapsed + 10))
        if [ "$elapsed" -ge "$timeout_secs" ]; then
            log "ERROR" "Claude session timed out after ${timeout_secs}s (PID: $claude_pid) — killing"
            kill "$claude_pid" 2>/dev/null
            wait "$claude_pid" 2>/dev/null
            CLAUDE_EXIT=124
            CLAUDE_OUTPUT=$(cat "$output_file" 2>/dev/null)
            rm -f "$prompt_file" "$output_file"
            return 124
        fi
        # Log progress every 2 minutes
        if [ $((elapsed % 120)) -eq 0 ]; then
            log "INFO" "Claude session still running (${elapsed}s elapsed, PID: $claude_pid)"
        fi
    done

    wait "$claude_pid"
    CLAUDE_EXIT=$?
    CLAUDE_OUTPUT=$(cat "$output_file" 2>/dev/null)
    rm -f "$prompt_file" "$output_file"
    local duration=$((elapsed))
    log "INFO" "Claude session finished in ${duration}s (exit: $CLAUDE_EXIT)"
    return $CLAUDE_EXIT
}

# Post a comment on a feedback item
post_feedback_comment() {
    local feedback_id="$1"
    local content="$2"
    local author_type="${3:-agent}"

    log "INFO" "Posting ${author_type} comment on feedback #${feedback_id}"

    local json_data=$(jq -n --arg c "$content" --arg t "$author_type" '{content: $c, author_type: $t}')

    local response=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
        -H "Content-Type: application/json" \
        -d "$json_data" \
        "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${feedback_id}/comments")

    local http_code=$(echo "$response" | tail -n1)

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        log "INFO" "Comment posted on feedback #${feedback_id}"
        return 0
    else
        log "ERROR" "Failed to post comment on feedback #${feedback_id} (HTTP $http_code)"
        return 1
    fi
}

# Fetch comments for a feedback item
fetch_feedback_comments() {
    local feedback_id="$1"

    local response=$(curl -s -w "\n%{http_code}" \
        -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
        -H "Accept: application/json" \
        "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${feedback_id}/comments")

    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')

    if [ "$http_code" = "200" ]; then
        echo "$body"
    else
        echo "[]"
    fi
}

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
    local project=$(echo "$json" | jq -r '.meta.project // "rondo-club"')

    if [ "$feedback_type" = "bug" ]; then
        echo "# Bug Report #${id}: ${title}"
    else
        echo "# Feature Request #${id}: ${title}"
    fi

    echo ""
    echo "**Status:** $(echo "$json" | jq -r '.meta.status') | **Priority:** $(echo "$json" | jq -r '.meta.priority') | **Project:** ${project} | **Submitted:** $(echo "$json" | jq -r '.date')"
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

    # Include conversation history if any
    local comments=$(fetch_feedback_comments "$id")
    local comment_count=$(echo "$comments" | jq 'length')

    if [ "$comment_count" -gt 0 ] 2>/dev/null; then
        echo "## Conversation History"
        echo ""
        echo "$comments" | jq -r '.[] | "**\(.author_type | if . == "agent" then "Agent" else "User: \(.author_name)" end)** (\(.created)):\n\(.content)\n"'
        echo ""
    fi

    # Include agent instructions
    local agent_prompt="$PROJECT_ROOT/.claude/agent-prompt.md"
    if [ -f "$agent_prompt" ]; then
        echo "---"
        echo ""
        cat "$agent_prompt"
    fi
}

# Parse Claude's response for status, PR URL, and question
parse_claude_response() {
    local response="$1"

    # Extract status
    if echo "$response" | grep -qi "STATUS:.*IN_REVIEW"; then
        PARSED_STATUS="in_review"
    elif echo "$response" | grep -qi "STATUS:.*RESOLVED"; then
        PARSED_STATUS="resolved"
    elif echo "$response" | grep -qi "STATUS:.*NEEDS_INFO"; then
        PARSED_STATUS="needs_info"
    elif echo "$response" | grep -qi "STATUS:.*DECLINED"; then
        PARSED_STATUS="declined"
    else
        PARSED_STATUS=""
    fi

    # Extract PR URL
    PARSED_PR_URL=$(echo "$response" | grep -i "PR_URL:" | tail -1 | sed 's/.*PR_URL:\s*//' | tr -d '[:space:]')

    # Extract question
    PARSED_QUESTION=$(echo "$response" | grep -i "QUESTION:" | tail -1 | sed 's/.*QUESTION:\s*//')
}

# Process a single feedback item with Claude
process_feedback_item() {
    local feedback_json="$1"
    local is_single="$2"

    # Get feedback ID, title, and project
    if [ "$is_single" = "true" ]; then
        CURRENT_FEEDBACK_ID=$(echo "$feedback_json" | jq -r '.id')
    else
        CURRENT_FEEDBACK_ID=$(echo "$feedback_json" | jq -r '.[0].id')
    fi
    local title=$(echo "$feedback_json" | jq -r "if type == \"array\" then .[0].title else .title end")
    local project=$(echo "$feedback_json" | jq -r "if type == \"array\" then .[0].meta.project else .meta.project end // \"rondo-club\"")
    ORIGINAL_STATUS="approved"

    # Resolve project directory
    local project_dir
    case "$project" in
        rondo-sync) project_dir="$(dirname "$PROJECT_ROOT")/rondo-sync" ;;
        website)    project_dir="$(dirname "$PROJECT_ROOT")/website" ;;
        *)          project_dir="$PROJECT_ROOT" ;;
    esac

    if [ ! -d "$project_dir" ]; then
        log "ERROR" "Project directory not found: $project_dir"
        echo -e "${RED}Error: Project directory not found: $project_dir${NC}" >&2
        return 1
    fi

    log "INFO" "Processing feedback #${CURRENT_FEEDBACK_ID}: \"${title}\" (project: ${project})"
    echo -e "${GREEN}Processing feedback #${CURRENT_FEEDBACK_ID}: ${title} (${project})${NC}" >&2

    # Ensure clean main in the project directory
    cd "$project_dir"
    if ! ensure_clean_main; then
        cd "$PROJECT_ROOT"
        return 1
    fi

    # Set status to in_progress to prevent re-pickup
    update_feedback_status "$CURRENT_FEEDBACK_ID" "in_progress"

    # Format the prompt
    local output=$(format_feedback_for_claude "$feedback_json" "$is_single")

    # Run Claude in the project directory
    cd "$project_dir"
    log "INFO" "Starting Claude Code session for feedback #${CURRENT_FEEDBACK_ID} in ${project_dir}"
    echo -e "${YELLOW}Starting Claude Code session in ${project_dir}...${NC}" >&2

    # Write prompt to temp file, run Claude with timeout
    local prompt_file=$(mktemp)
    local output_file=$(mktemp)
    printf '%s' "$output" > "$prompt_file"

    run_claude "$prompt_file" "$output_file" 600

    # Display Claude's output
    echo "$CLAUDE_OUTPUT"

    if [ $CLAUDE_EXIT -ne 0 ]; then
        log "ERROR" "Claude session failed (exit code: $CLAUDE_EXIT)"
        echo -e "${RED}Claude session failed (exit code: $CLAUDE_EXIT)${NC}" >&2
        # Reset status back since we failed
        update_feedback_status "$CURRENT_FEEDBACK_ID" "$ORIGINAL_STATUS"
        cd "$project_dir" && git checkout main 2>/dev/null
        cd "$PROJECT_ROOT"
        CURRENT_FEEDBACK_ID=""
        return 1
    fi

    log "INFO" "Claude session completed successfully"

    # Parse response
    parse_claude_response "$CLAUDE_OUTPUT"

    case "$PARSED_STATUS" in
        in_review)
            update_feedback_status "$CURRENT_FEEDBACK_ID" "in_review"
            if [ -n "$PARSED_PR_URL" ]; then
                update_feedback_meta "$CURRENT_FEEDBACK_ID" "$PARSED_PR_URL" ""
                log "INFO" "Feedback #${CURRENT_FEEDBACK_ID} in review with PR: ${PARSED_PR_URL}"
                # Request Copilot code review
                local pr_number=$(echo "$PARSED_PR_URL" | grep -oE '[0-9]+$')
                if [ -n "$pr_number" ]; then
                    log "INFO" "Requesting Copilot review for PR #${pr_number}"
                    gh copilot-review "$pr_number" 2>&1 || log "WARN" "Copilot review request failed for PR #${pr_number}"
                fi
            fi
            ;;
        resolved)
            update_feedback_status "$CURRENT_FEEDBACK_ID" "resolved"
            if [ -n "$PARSED_PR_URL" ]; then
                update_feedback_meta "$CURRENT_FEEDBACK_ID" "$PARSED_PR_URL" ""
                log "INFO" "Feedback #${CURRENT_FEEDBACK_ID} resolved with PR: ${PARSED_PR_URL}"
            fi
            ;;
        needs_info)
            update_feedback_status "$CURRENT_FEEDBACK_ID" "needs_info"
            if [ -n "$PARSED_QUESTION" ]; then
                post_feedback_comment "$CURRENT_FEEDBACK_ID" "$PARSED_QUESTION" "agent"
                log "INFO" "Feedback #${CURRENT_FEEDBACK_ID} needs info: \"${PARSED_QUESTION}\""
            fi
            ;;
        declined)
            update_feedback_status "$CURRENT_FEEDBACK_ID" "declined"
            log "INFO" "Feedback #${CURRENT_FEEDBACK_ID} declined"
            ;;
        *)
            # No status parsed — reset to approved for next run
            update_feedback_status "$CURRENT_FEEDBACK_ID" "$ORIGINAL_STATUS"
            log "INFO" "No final status for feedback #${CURRENT_FEEDBACK_ID} — remains in queue"
            echo -e "${YELLOW}No final status. Feedback remains in queue for next run.${NC}" >&2
            ;;
    esac

    # Return to main and clean up in project dir
    cd "$project_dir"
    git checkout main 2>/dev/null
    git branch --merged main | grep -E '^\s+(feedback|optimize)/' | xargs -r git branch -d 2>/dev/null

    # Return to rondo-club root
    cd "$PROJECT_ROOT"

    CURRENT_FEEDBACK_ID=""
    ORIGINAL_STATUS=""
}

# Map of projects to directories and file patterns
OPTIMIZATION_PROJECTS=(
    "rondo-club:$PROJECT_ROOT"
    "rondo-sync:$(dirname "$PROJECT_ROOT")/rondo-sync"
    "website:$(dirname "$PROJECT_ROOT")/website"
)

# Resolve the feedback item associated with a PR branch (feedback/<id>-slug)
resolve_feedback_for_branch() {
    local branch="$1"
    local feedback_id

    # Extract feedback ID from branch name (feedback/1234-some-slug → 1234)
    feedback_id=$(echo "$branch" | sed -n 's|^feedback/\([0-9]*\).*|\1|p')
    if [ -n "$feedback_id" ]; then
        log "INFO" "Resolving feedback #${feedback_id} after PR merge"
        update_feedback_status "$feedback_id" "resolved"
    fi
}

# Merge a PR via squash, pull main, and deploy
merge_and_deploy() {
    local pr_number="$1"

    log "INFO" "Merging PR #${pr_number} via squash"
    echo -e "${GREEN}Merging PR #${pr_number}...${NC}" >&2

    if ! gh pr merge "$pr_number" --repo RondoHQ/rondo-club --squash --delete-branch; then
        log "ERROR" "Failed to merge PR #${pr_number}"
        echo -e "${RED}Failed to merge PR #${pr_number}${NC}" >&2
        return 1
    fi

    log "INFO" "PR #${pr_number} merged — pulling main and deploying"
    cd "$PROJECT_ROOT"
    git checkout main 2>/dev/null
    git pull --ff-only 2>/dev/null
    bin/deploy.sh

    log "INFO" "Deploy complete after merging PR #${pr_number}"
    echo -e "${GREEN}Deploy complete after merging PR #${pr_number}${NC}" >&2
}

# Format review comments into a prompt for Claude
format_review_prompt() {
    local pr_number="$1"
    local branch="$2"
    local copilot_comments="$3"

    local prompt="# PR Review Fix: PR #${pr_number}

**Branch:** \`${branch}\`

You are on the \`${branch}\` branch. Below are Copilot's inline review comments on this PR.
Evaluate each comment and fix what's worth fixing.

## Copilot Review Comments

"

    # Format each comment: file, line, body
    prompt+=$(echo "$copilot_comments" | jq -r '.[] | "### \(.path) (line \(.line // .original_line // "N/A"))\n\(.body)\n"')

    # Append review-fix agent prompt
    local review_prompt_file="$PROJECT_ROOT/.claude/review-fix-prompt.md"
    if [ -f "$review_prompt_file" ]; then
        prompt+="
---

$(cat "$review_prompt_file")"
    fi

    echo "$prompt"
}

# Process open PRs that have unhandled Copilot review feedback
process_pr_reviews() {
    log "INFO" "Checking open PRs for Copilot review feedback"
    echo -e "${YELLOW}Checking open PRs for Copilot review feedback...${NC}" >&2

    local tracker="$PROJECT_ROOT/logs/pr-reviews-tracker.json"

    # Initialize tracker if needed
    if [ ! -f "$tracker" ]; then
        echo '{"processed_reviews": []}' > "$tracker"
    fi

    # Get open PRs
    local prs
    prs=$(gh pr list --repo RondoHQ/rondo-club --json number,headRefName --state open 2>/dev/null)
    if [ $? -ne 0 ] || [ -z "$prs" ]; then
        log "WARN" "Failed to list PRs from GitHub"
        return 0
    fi

    local pr_count
    pr_count=$(echo "$prs" | jq 'length')

    if [ "$pr_count" = "0" ] || [ "$pr_count" = "null" ]; then
        log "INFO" "No open PRs to review"
        echo -e "${GREEN}No open PRs to review.${NC}" >&2
        return 0
    fi

    local reviews_processed=0

    # Process each PR
    echo "$prs" | jq -c '.[]' | while read -r pr; do
        local pr_number
        pr_number=$(echo "$pr" | jq -r '.number')
        local branch
        branch=$(echo "$pr" | jq -r '.headRefName')

        # Only process feedback/* and optimize/* branches
        if [[ "$branch" != feedback/* ]] && [[ "$branch" != optimize/* ]]; then
            continue
        fi

        # Check for Copilot reviews
        local reviews
        reviews=$(gh api "repos/RondoHQ/rondo-club/pulls/${pr_number}/reviews" 2>/dev/null)
        if [ $? -ne 0 ] || [ -z "$reviews" ]; then
            continue
        fi

        local copilot_review
        copilot_review=$(echo "$reviews" | jq -r '[.[] | select(.user.login == "copilot-pull-request-reviewer[bot]")] | sort_by(.submitted_at) | last')

        if [ "$copilot_review" = "null" ] || [ -z "$copilot_review" ]; then
            continue
        fi

        local review_id
        review_id=$(echo "$copilot_review" | jq -r '.id')

        # Check if already processed
        if jq -e --argjson id "$review_id" '.processed_reviews[] | select(.review_id == $id)' "$tracker" > /dev/null 2>&1; then
            continue
        fi

        # Fetch inline comments from Copilot
        local comments
        comments=$(gh api "repos/RondoHQ/rondo-club/pulls/${pr_number}/comments" 2>/dev/null)
        if [ $? -ne 0 ] || [ -z "$comments" ]; then
            comments="[]"
        fi

        local copilot_comments
        copilot_comments=$(echo "$comments" | jq '[.[] | select(.user.login == "Copilot")]')
        local comment_count
        comment_count=$(echo "$copilot_comments" | jq 'length')

        if [ "$comment_count" = "0" ]; then
            # Reviewed but no inline comments — clean review, merge and deploy
            log "INFO" "PR #${pr_number} — Copilot review clean, merging and deploying"
            echo -e "${GREEN}PR #${pr_number} — Copilot review clean, merging and deploying${NC}" >&2
            merge_and_deploy "$pr_number"
            resolve_feedback_for_branch "$branch"
            local now
            now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
            jq --argjson num "$pr_number" --argjson rid "$review_id" --arg t "$now" \
                '.processed_reviews += [{"pr_number": $num, "review_id": $rid, "processed_at": $t, "action": "merged"}]' \
                "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
            continue
        fi

        log "INFO" "PR #${pr_number} has ${comment_count} Copilot review comments — processing"
        echo -e "${GREEN}PR #${pr_number} has ${comment_count} Copilot review comments — processing${NC}" >&2

        # Ensure clean main, then checkout PR branch
        if ! ensure_clean_main; then
            log "ERROR" "Cannot process PR #${pr_number} — working directory not clean"
            continue
        fi

        git fetch origin "$branch" 2>/dev/null
        git checkout "$branch" 2>/dev/null || git checkout -b "$branch" "origin/$branch" 2>/dev/null
        git pull --ff-only 2>/dev/null

        # Format prompt with review comments + review-fix instructions
        local prompt
        prompt=$(format_review_prompt "$pr_number" "$branch" "$copilot_comments")

        # Run Claude with timeout
        local prompt_file
        prompt_file=$(mktemp)
        local output_file
        output_file=$(mktemp)
        printf '%s' "$prompt" > "$prompt_file"

        run_claude "$prompt_file" "$output_file" 600
        local exit_code=$CLAUDE_EXIT
        local output="$CLAUDE_OUTPUT"

        echo "$output"

        local action="processed"
        if [ $exit_code -ne 0 ]; then
            log "ERROR" "Claude session failed for PR #${pr_number} review (exit code: $exit_code) — assigning to jdevalk"
            echo -e "${YELLOW}PR #${pr_number} — Claude failed, assigning to jdevalk${NC}" >&2
            gh pr edit "$pr_number" --repo RondoHQ/rondo-club --add-assignee jdevalk 2>/dev/null
            action="assigned"
        elif echo "$output" | grep -qi "SAFE_TO_MERGE:.*yes"; then
            log "INFO" "PR #${pr_number} — safe to merge, merging and deploying"
            echo -e "${GREEN}PR #${pr_number} — safe to merge, merging and deploying${NC}" >&2
            merge_and_deploy "$pr_number"
            resolve_feedback_for_branch "$branch"
            action="merged"
        else
            log "INFO" "PR #${pr_number} — not safe to auto-merge, assigning to jdevalk"
            echo -e "${YELLOW}PR #${pr_number} — assigning to jdevalk for review${NC}" >&2
            gh pr edit "$pr_number" --repo RondoHQ/rondo-club --add-assignee jdevalk 2>/dev/null
            action="assigned"
        fi

        # Mark review as processed in tracker
        local now
        now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
        jq --argjson num "$pr_number" --argjson rid "$review_id" --arg t "$now" --arg a "$action" \
            '.processed_reviews += [{"pr_number": $num, "review_id": $rid, "processed_at": $t, "action": $a}]' \
            "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"

        reviews_processed=$((reviews_processed + 1))

        # Return to main and clean up
        cd "$PROJECT_ROOT"
        git checkout main 2>/dev/null
        git branch --merged main | grep -E '^\s+(feedback|optimize)/' | xargs -r git branch -d 2>/dev/null
    done

    log "INFO" "PR review processing complete (${reviews_processed} reviews processed)"
}

# Run optimization mode (when no feedback items are pending)
run_optimization() {
    log "INFO" "No feedback items — entering optimization mode"
    echo -e "${YELLOW}No feedback items. Running code optimization...${NC}" >&2

    local tracker="$PROJECT_ROOT/logs/optimization-tracker.json"
    local daily_run_limit=25
    local daily_pr_limit=10

    # Initialize tracker if needed
    if [ ! -f "$tracker" ]; then
        echo '{"reviewed_files": {}, "last_run": null, "daily_runs": {}, "daily_prs": {}}' > "$tracker"
    fi

    # Check daily limits
    local today=$(date +%Y-%m-%d)
    local today_runs=$(jq -r --arg d "$today" '.daily_runs[$d] // 0' "$tracker")
    local today_prs=$(jq -r --arg d "$today" '.daily_prs[$d] // 0' "$tracker")

    if [ "$today_runs" -ge "$daily_run_limit" ]; then
        log "INFO" "Daily optimization run limit reached ($today_runs/$daily_run_limit) — skipping"
        echo -e "${GREEN}Daily optimization run limit reached ($today_runs/$daily_run_limit). Skipping.${NC}" >&2
        return 0
    fi

    if [ "$today_prs" -ge "$daily_pr_limit" ]; then
        log "INFO" "Daily optimization PR limit reached ($today_prs/$daily_pr_limit) — skipping"
        echo -e "${GREEN}Daily optimization PR limit reached ($today_prs/$daily_pr_limit). Skipping.${NC}" >&2
        return 0
    fi

    # Find the first unreviewd file across all projects
    local target_file=""
    local target_project=""
    local target_dir=""

    for project_entry in "${OPTIMIZATION_PROJECTS[@]}"; do
        local proj_name="${project_entry%%:*}"
        local proj_dir="${project_entry#*:}"

        [ ! -d "$proj_dir" ] && continue

        # Build file list for this project
        local proj_files=""
        case "$proj_name" in
            rondo-club)
                proj_files=$(find "$proj_dir/includes" -name "*.php" -type f 2>/dev/null | sort)
                proj_files+=$'\n'
                proj_files+=$(find "$proj_dir/src" \( -name "*.jsx" -o -name "*.js" \) -type f 2>/dev/null | sort)
                ;;
            rondo-sync)
                proj_files=$(find "$proj_dir/src" -name "*.js" -type f 2>/dev/null | sort)
                ;;
            website)
                proj_files=$(find "$proj_dir/src" \( -name "*.astro" -o -name "*.ts" -o -name "*.tsx" \) -type f 2>/dev/null | sort)
                ;;
        esac

        while IFS= read -r file; do
            [ -z "$file" ] && continue
            local relative_file="${file#$proj_dir/}"
            local tracker_key="${proj_name}:${relative_file}"
            if ! jq -e --arg f "$tracker_key" '.reviewed_files[$f]' "$tracker" > /dev/null 2>&1; then
                target_file="$relative_file"
                target_project="$proj_name"
                target_dir="$proj_dir"
                break 2
            fi
        done <<< "$proj_files"
    done

    if [ -z "$target_file" ]; then
        log "INFO" "All files across all projects have been reviewed — resetting tracker"
        jq '.reviewed_files = {}' "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
        echo -e "${GREEN}All files reviewed. Tracker reset for next cycle.${NC}" >&2
        return 0
    fi

    log "INFO" "Optimization target: \"${target_project}:${target_file}\""
    echo -e "${YELLOW}Reviewing: ${target_project}/${target_file}${NC}" >&2

    # Ensure clean main in target project
    cd "$target_dir"
    if ! ensure_clean_main; then
        cd "$PROJECT_ROOT"
        return 1
    fi

    # Build optimization prompt
    local optimize_prompt_file="$PROJECT_ROOT/.claude/optimize-prompt.md"
    local prompt=""
    if [ -f "$optimize_prompt_file" ]; then
        prompt=$(cat "$optimize_prompt_file")
    else
        prompt="Review the following file for simplification and optimization opportunities."
    fi

    prompt="${prompt}

## Target File
\`${target_file}\` (project: ${target_project})

Review this file and create a PR if you find confident improvements. If no changes are needed, just respond with STATUS: NO_CHANGES."

    # Run Claude in the project directory with timeout
    local prompt_file=$(mktemp)
    local output_file=$(mktemp)
    printf '%s' "$prompt" > "$prompt_file"

    run_claude "$prompt_file" "$output_file" 300

    echo "$CLAUDE_OUTPUT"

    # Request Copilot review if a PR was created and increment PR counter
    local created_pr=false
    local opt_pr_url=$(echo "$CLAUDE_OUTPUT" | grep -oE 'https://github.com/[^ ]*pull/[0-9]+' | head -1)
    if [ -n "$opt_pr_url" ]; then
        created_pr=true
        local opt_pr_number=$(echo "$opt_pr_url" | grep -oE '[0-9]+$')
        if [ -n "$opt_pr_number" ]; then
            log "INFO" "Requesting Copilot review for optimization PR #${opt_pr_number}"
            gh copilot-review "$opt_pr_number" 2>&1 || log "WARN" "Copilot review request failed for PR #${opt_pr_number}"
        fi
    fi

    # Mark file as reviewed and increment daily counters
    local now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    local tracker_key="${target_project}:${target_file}"
    if [ "$created_pr" = true ]; then
        jq --arg f "$tracker_key" --arg t "$now" --arg d "$today" \
            '.reviewed_files[$f] = true | .last_run = $t | .daily_runs[$d] = ((.daily_runs[$d] // 0) + 1) | .daily_prs[$d] = ((.daily_prs[$d] // 0) + 1)' \
            "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
    else
        jq --arg f "$tracker_key" --arg t "$now" --arg d "$today" \
            '.reviewed_files[$f] = true | .last_run = $t | .daily_runs[$d] = ((.daily_runs[$d] // 0) + 1)' \
            "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
    fi

    # Return to main in project dir, then back to rondo-club
    cd "$target_dir"
    git checkout main 2>/dev/null
    git branch --merged main | grep -E '^\s+(feedback|optimize)/' | xargs -r git branch -d 2>/dev/null
    cd "$PROJECT_ROOT"

    log "INFO" "Optimization run complete for: \"${target_project}:${target_file}\""
}

# Loop mode: process items until none left
if [ "$LOOP_MODE" = true ]; then
    log "INFO" "Starting loop mode - will process all feedback items"
    echo -e "${GREEN}Loop mode enabled - processing all feedback items${NC}" >&2
    LOOP_COUNTER=0
fi

# Main loop - runs once normally, loops when LOOP_MODE=true
while true; do
    # In loop mode, increment counter and log progress
    if [ "$LOOP_MODE" = true ]; then
        LOOP_COUNTER=$((LOOP_COUNTER + 1))
        echo "" >&2
        echo -e "${GREEN}=== Processing item #${LOOP_COUNTER} ===${NC}" >&2
        log "INFO" "Loop iteration #${LOOP_COUNTER}"
    fi

    # Build API URL - fetch 1 item, oldest first
    API_BASE="${RONDO_API_URL}/wp-json/rondo/v1/feedback"

    if [ -n "$FEEDBACK_ID" ]; then
        API_URL="${API_BASE}/${FEEDBACK_ID}"
    else
        API_URL="${API_BASE}?per_page=1&orderby=date&order=asc"
        [ -n "$STATUS" ] && API_URL="${API_URL}&status=${STATUS}"
        [ -n "$TYPE" ] && API_URL="${API_URL}&type=${TYPE}"
    fi

    # Fetch feedback
    local filter_desc="status=$STATUS"
    [ -n "$TYPE" ] && filter_desc="${filter_desc}, type=$TYPE"
    log "INFO" "Fetching feedback from API ($filter_desc)"
    echo -e "${YELLOW}Fetching feedback from Rondo Club...${NC}" >&2

    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
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
        if [ "$LOOP_MODE" = true ]; then
            local_counter=$((LOOP_COUNTER - 1))
            echo -e "${GREEN}No more feedback items. Processed ${local_counter} items.${NC}" >&2
            log "INFO" "Loop completed - processed ${local_counter} items"

            # Process any open PRs with Copilot review feedback
            process_pr_reviews

            # Run optimization if enabled and no feedback was found
            if [ "$OPTIMIZE_MODE" = true ]; then
                run_optimization
            fi

            SCRIPT_COMPLETED=true
            break
        else
            echo -e "${GREEN}No feedback items found matching your criteria.${NC}" >&2

            # Process any open PRs with Copilot review feedback
            if [ "$RUN_CLAUDE" = true ]; then
                process_pr_reviews
            fi

            # Run optimization if enabled
            if [ "$OPTIMIZE_MODE" = true ] && [ "$RUN_CLAUDE" = true ]; then
                run_optimization
            fi

            SCRIPT_COMPLETED=true
            exit 0
        fi
    fi

    # Get the feedback ID for display
    if [ -n "$FEEDBACK_ID" ]; then
        DISPLAY_ID="$FEEDBACK_ID"
    else
        DISPLAY_ID=$(echo "$BODY" | jq -r '.[0].id')
    fi
    DISPLAY_TITLE=$(echo "$BODY" | jq -r 'if type == "array" then .[0].title else .title end')
    log "INFO" "Retrieved feedback #${DISPLAY_ID}: \"${DISPLAY_TITLE}\""
    echo -e "${GREEN}Retrieved feedback #${DISPLAY_ID}${NC}" >&2

    # Output based on format
    if [ "$OUTPUT_FORMAT" = "json" ]; then
        echo "$BODY" | jq .
        SCRIPT_COMPLETED=true
        exit 0
    fi

    # Either output to stdout or run Claude
    if [ "$RUN_CLAUDE" = true ]; then
        if [ -n "$FEEDBACK_ID" ]; then
            process_feedback_item "$BODY" "true"
        else
            process_feedback_item "$BODY" "false"
        fi

        log "INFO" "=== Feedback item processing finished ==="
        SCRIPT_COMPLETED=true

        # In loop mode, continue to next item; otherwise break
        if [ "$LOOP_MODE" = true ]; then
            SCRIPT_COMPLETED=false  # Reset so cleanup trap doesn't skip
            echo -e "${YELLOW}Checking for next feedback item...${NC}" >&2
            sleep 2  # Brief pause between items
        else
            break
        fi
    else
        # Non-Claude output mode (pipes, JSON, etc)
        if [ -n "$FEEDBACK_ID" ]; then
            OUTPUT=$(format_feedback_for_claude "$BODY" "true")
        else
            OUTPUT=$(format_feedback_for_claude "$BODY" "false")
        fi
        echo "$OUTPUT"
        SCRIPT_COMPLETED=true
        break
    fi
done  # End of main loop

exit
}
