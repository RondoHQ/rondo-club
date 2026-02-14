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
#   bin/get-feedback.sh --loop --optimize  # Process all items, review PRs, fix PHP errors, then optimize
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
        git branch --merged main | grep -E '^\s+(feedback|optimize|fix)/' | xargs -r git branch -d 2>/dev/null

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
PHP_ERRORS_MODE=true

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
        --no-php-errors)
            PHP_ERRORS_MODE=false
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
            echo "  --optimize         When no feedback items, fix PHP errors and review code for optimization PRs"
            echo "  --no-php-errors    Skip PHP error checking from production php_errorlog"
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

    # Force back to a clean main before doing anything
    cd "$PROJECT_ROOT"
    git reset --hard HEAD 2>/dev/null
    git checkout main 2>/dev/null
    git pull --ff-only 2>/dev/null
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
# Usage: run_claude <prompt_file> <output_file> [timeout_seconds] [model] [extra_flags]
# Sets CLAUDE_EXIT and CLAUDE_OUTPUT globals
run_claude() {
    local prompt_file="$1"
    local output_file="$2"
    local timeout_secs="${3:-600}"
    local model="${4:-}"
    local extra_flags="${5:-}"

    CLAUDE_BIN="${CLAUDE_PATH:-claude}"

    local model_args=()
    if [ -n "$model" ]; then
        model_args=(--model "$model")
        log "INFO" "Using model: $model"
    fi

    local extra_args=()
    if [ -n "$extra_flags" ]; then
        read -ra extra_args <<< "$extra_flags"
        log "INFO" "Extra flags: $extra_flags"
    fi

    "$CLAUDE_BIN" --print --dangerously-skip-permissions "${model_args[@]}" "${extra_args[@]}" < "$prompt_file" > "$output_file" 2>&1 &
    local claude_pid=$!
    local elapsed=0

    while kill -0 "$claude_pid" 2>/dev/null; do
        sleep 10
        elapsed=$((elapsed + 10))
        if [ "$elapsed" -ge "$timeout_secs" ]; then
            log "ERROR" "Claude session timed out after ${timeout_secs}s (PID: $claude_pid) — killing"
            kill "$claude_pid" 2>/dev/null
            wait "$claude_pid" 2>/dev/null
            git reset --hard HEAD 2>/dev/null
            git checkout main 2>/dev/null
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

    # --- Phase 1: Planning with Sonnet ---
    log "INFO" "Starting planning session for feedback #${CURRENT_FEEDBACK_ID} in ${project_dir}"
    echo -e "${YELLOW}Phase 1/2: Planning with Sonnet...${NC}" >&2

    # Load codebase map if available
    local codebase_map=""
    local codebase_map_file="$PROJECT_ROOT/.claude/codebase-map.md"
    if [ -f "$codebase_map_file" ]; then
        codebase_map=$(cat "$codebase_map_file")
        log "INFO" "Loaded codebase map ($(wc -l < "$codebase_map_file") lines)"
    fi

    local plan_prompt="${output}

---
"

    # Inject codebase map if available
    if [ -n "$codebase_map" ]; then
        plan_prompt+="
## Codebase Reference

${codebase_map}

---
"
    fi

    plan_prompt+="
## YOUR TASK: Create an Implementation Plan

You are in PLANNING MODE. Do NOT make any code changes, do NOT create branches, do NOT commit anything."

    # Add exploration guidance when codebase map is available
    if [ -n "$codebase_map" ]; then
        plan_prompt+="

A codebase map is provided above — use it to identify relevant files instead of exploring the codebase. Only read files you need to understand for this specific change."
    fi

    plan_prompt+="

### Step 1: Decide if you have enough information

**Before planning anything**, check if the feedback is clear enough to act on. Most feedback IS clear enough — prefer action over asking questions.

Only ask for clarification (STATUS: NEEDS_INFO) when you TRULY cannot proceed:
- The description is so vague you cannot identify what to change at all
- There are multiple contradictory interpretations and picking wrong would cause harm
- The requested feature requires design decisions that only the user can make (e.g., specific business rules, pricing, permissions)

**Do NOT ask about:**
- Edge cases you can handle with reasonable defaults
- Migration of old data — pick the simplest safe approach (usually: remove/ignore)
- Implementation details you can decide yourself
- Things that are obvious from the codebase

**Err on the side of acting.** Make reasonable choices, document them in your PR description, and let the reviewer adjust if needed.

If clarification is truly needed, output ONLY this (no plan):
STATUS: NEEDS_INFO
QUESTION: Your specific question — be concrete about what you need to know

If the feedback should be declined (out of scope, not feasible, already works as designed), output ONLY:
STATUS: DECLINED

### Step 2: Create the plan

If and only if you are confident you understand exactly what needs to change, produce a plan:

1. **Files to modify** — List every file that needs changes, with the specific changes needed
2. **New files** (if any) — What new files need to be created and what they should contain
3. **Implementation steps** — Numbered step-by-step instructions specific enough for another developer to follow
4. **Testing** — How to verify the changes work (build commands, what to check)
5. **PR details** — Suggested branch name, PR title, and PR description

Be specific about code changes — include function names, class names, and describe the logic. Do NOT include actual code blocks, just describe what needs to change."

    local prompt_file=$(mktemp)
    local output_file=$(mktemp)
    printf '%s' "$plan_prompt" > "$prompt_file"

    run_claude "$prompt_file" "$output_file" 600 "sonnet" "--effort low"

    local plan_output="$CLAUDE_OUTPUT"
    local plan_exit=$CLAUDE_EXIT

    if [ $plan_exit -ne 0 ]; then
        log "ERROR" "Planning session failed (exit code: $plan_exit)"
        echo -e "${RED}Planning session failed (exit code: $plan_exit)${NC}" >&2
        update_feedback_status "$CURRENT_FEEDBACK_ID" "$ORIGINAL_STATUS"
        cd "$project_dir" && git checkout main 2>/dev/null
        cd "$PROJECT_ROOT"
        CURRENT_FEEDBACK_ID=""
        return 1
    fi

    log "INFO" "Planning session completed"

    # Check if the plan indicates NEEDS_INFO or DECLINED — skip implementation
    if echo "$plan_output" | grep -qi "STATUS:.*NEEDS_INFO\|STATUS:.*DECLINED"; then
        log "INFO" "Planning phase returned early status — skipping implementation"
        echo "$plan_output"
        CLAUDE_OUTPUT="$plan_output"
        CLAUDE_EXIT=0
    else
        # --- Phase 2: Implementation with Sonnet ---
        log "INFO" "Starting Sonnet implementation session for feedback #${CURRENT_FEEDBACK_ID}"
        echo -e "${YELLOW}Phase 2/2: Implementing with Sonnet...${NC}" >&2

        local impl_prompt="${output}

---

## Implementation Plan (from planning phase)

${plan_output}

---

## YOUR TASK: Execute the Plan

Follow the implementation plan above. The plan was created by a senior engineer who analyzed the feedback and codebase. Execute it step by step:

1. Create the branch as specified in the plan
2. Make all the code changes described
3. Run \`npm run build\` to verify (for rondo-club)
4. Commit and push
5. Create the PR as described in the plan
6. Output your status (STATUS: IN_REVIEW with PR_URL, or STATUS: NEEDS_INFO/DECLINED if you hit a blocker)"

        prompt_file=$(mktemp)
        output_file=$(mktemp)
        printf '%s' "$impl_prompt" > "$prompt_file"

        run_claude "$prompt_file" "$output_file" 600 "sonnet"

        # Display Claude's output
        echo "$CLAUDE_OUTPUT"
    fi

    if [ $CLAUDE_EXIT -ne 0 ]; then
        log "ERROR" "Claude session failed (exit code: $CLAUDE_EXIT)"
        echo -e "${RED}Claude session failed (exit code: $CLAUDE_EXIT)${NC}" >&2
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
    git branch --merged main | grep -E '^\s+(feedback|optimize|fix)/' | xargs -r git branch -d 2>/dev/null

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

# Prepare a PR branch for merge by merging main into it and resolving conflicts
# Uses Claude to resolve any merge conflicts
prepare_branch_for_merge() {
    local pr_number="$1"
    local pr_branch="$2"

    cd "$PROJECT_ROOT"
    git fetch origin 2>/dev/null

    # Check out the PR branch with latest from origin
    git checkout "$pr_branch" 2>/dev/null || git checkout -b "$pr_branch" "origin/$pr_branch" 2>/dev/null
    git reset --hard "origin/$pr_branch" 2>/dev/null

    # Merge main into the PR branch — Claude resolves any conflicts
    if ! git merge origin/main --no-edit 2>/dev/null; then
        log "INFO" "Merge conflicts on PR #${pr_number} branch — running Claude to resolve"
        local prompt_file=$(mktemp)
        local output_file=$(mktemp)
        printf '%s' "There are git merge conflicts in this repository. Run git status to see the conflicted files, resolve all conflicts, then stage and commit the merge. Keep the intent of both the branch changes and main. Do not discard either side without good reason." > "$prompt_file"

        run_claude "$prompt_file" "$output_file" 300 "sonnet"

        if [ $CLAUDE_EXIT -ne 0 ]; then
            log "ERROR" "Claude failed to resolve merge conflicts on PR #${pr_number}"
            git merge --abort 2>/dev/null
            git checkout main 2>/dev/null
            return 1
        fi
    fi

    if ! git push origin "$pr_branch" 2>&1; then
        log "ERROR" "Failed to push branch $pr_branch for PR #${pr_number}"
        git checkout main 2>/dev/null
        return 1
    fi
    git checkout main 2>/dev/null
    return 0
}

# Merge a PR via squash, pull main, and deploy
merge_and_deploy() {
    local pr_number="$1"

    log "INFO" "Merging PR #${pr_number} via squash"
    echo -e "${GREEN}Merging PR #${pr_number}...${NC}" >&2

    # Get the branch name for this PR
    local pr_branch
    pr_branch=$(gh pr view "$pr_number" --repo RondoHQ/rondo-club --json headRefName -q '.headRefName' 2>/dev/null)

    if [ -n "$pr_branch" ]; then
        if ! prepare_branch_for_merge "$pr_number" "$pr_branch"; then
            return 1
        fi

        # Give GitHub time to process the push and update mergeability
        sleep 10
    fi

    # Try to merge
    local merge_output
    merge_output=$(gh pr merge "$pr_number" --repo RondoHQ/rondo-club --squash --delete-branch 2>&1)
    local merge_exit=$?

    # If merge fails and we have a branch, retry once after re-preparing
    if [ $merge_exit -ne 0 ] && [ -n "$pr_branch" ]; then
        log "WARN" "First merge attempt failed for PR #${pr_number}: $merge_output — retrying"

        if ! prepare_branch_for_merge "$pr_number" "$pr_branch"; then
            return 1
        fi

        sleep 15

        merge_output=$(gh pr merge "$pr_number" --repo RondoHQ/rondo-club --squash --delete-branch 2>&1)
        merge_exit=$?
    fi

    if [ $merge_exit -ne 0 ]; then
        log "ERROR" "Failed to merge PR #${pr_number}: $merge_output"
        echo -e "${RED}Failed to merge PR #${pr_number}: $merge_output${NC}" >&2
        return 1
    fi

    # Verify PR is actually closed (catch false positives)
    local pr_state
    pr_state=$(gh pr view "$pr_number" --repo RondoHQ/rondo-club --json state -q '.state' 2>/dev/null)
    if [ "$pr_state" != "MERGED" ]; then
        log "ERROR" "PR #${pr_number} merge command succeeded but PR state is '$pr_state' — not actually merged"
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

# Resolve feedback items whose PRs were merged outside the script (e.g. manually)
resolve_merged_feedback_prs() {
    local repos="RondoHQ/rondo-club RondoHQ/rondo-sync RondoHQ/website"

    for repo in $repos; do
        local merged_prs
        merged_prs=$(gh pr list --repo "$repo" --json number,headRefName --state merged --search "head:feedback/" --limit 50 2>/dev/null)
        if [ $? -ne 0 ] || [ -z "$merged_prs" ] || [ "$merged_prs" = "[]" ]; then
            continue
        fi

        echo "$merged_prs" | jq -c '.[]' | while read -r pr; do
            local branch
            branch=$(echo "$pr" | jq -r '.headRefName')
            local pr_number
            pr_number=$(echo "$pr" | jq -r '.number')
            local feedback_id
            feedback_id=$(echo "$branch" | sed -n 's|^feedback/\([0-9]*\).*|\1|p')
            [ -z "$feedback_id" ] && continue

            # Check if still in_review on the WordPress side
            local status
            status=$(curl -sf \
                -u "${RONDO_API_USER}:${RONDO_API_PASSWORD}" \
                "${RONDO_API_URL}/wp-json/rondo/v1/feedback/${feedback_id}" 2>/dev/null | jq -r '.status // empty')
            if [ "$status" = "in_review" ]; then
                log "INFO" "PR #${pr_number} (${repo}#${pr_number}, ${branch}) was merged externally — resolving feedback #${feedback_id}"
                update_feedback_status "$feedback_id" "resolved"
            fi
        done
    done
}

# Process open PRs that have unhandled Copilot review feedback
process_pr_reviews() {
    # First, resolve any feedback items whose PRs were merged outside this script
    resolve_merged_feedback_prs

    log "INFO" "Checking open PRs for Copilot review feedback"
    echo -e "${YELLOW}Checking open PRs for Copilot review feedback...${NC}" >&2

    local tracker="$PROJECT_ROOT/logs/pr-reviews-tracker.json"

    # Initialize tracker if needed
    if [ ! -f "$tracker" ]; then
        echo '{"processed_reviews": []}' > "$tracker"
    fi

    # Get open PRs
    local prs
    prs=$(gh pr list --repo RondoHQ/rondo-club --json number,headRefName,title,createdAt --state open 2>/dev/null | jq 'sort_by(.createdAt)')
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

    # Process each PR (use fd 3 so subprocesses don't consume the pipe)
    while read -r pr <&3; do
        local pr_number
        pr_number=$(echo "$pr" | jq -r '.number')
        local branch
        branch=$(echo "$pr" | jq -r '.headRefName')
        local pr_title
        pr_title=$(echo "$pr" | jq -r '.title')

        # Only process feedback/* and optimize/* branches
        if [[ "$branch" != feedback/* ]] && [[ "$branch" != optimize/* ]] && [[ "$branch" != fix/* ]]; then
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

        # Check if already processed — but only skip if the action was NOT a failure
        # Since we're iterating open PRs, any "merged" entry here is stale (PR didn't actually merge)
        local tracked_action
        tracked_action=$(jq -r --argjson id "$review_id" '[.processed_reviews[] | select(.review_id == $id)] | last | .action // empty' "$tracker")
        if [ "$tracked_action" = "assigned" ]; then
            continue
        fi
        # Remove stale entries for this PR so we get a clean retry
        if [ -n "$tracked_action" ]; then
            log "INFO" "PR #${pr_number} still open but tracker shows '${tracked_action}' — retrying"
            jq --argjson num "$pr_number" '.processed_reviews = [.processed_reviews[] | select(.pr_number != $num)]' \
                "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
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
            log "INFO" "PR #${pr_number} (${pr_title}) — Copilot review clean, merging and deploying"
            echo -e "${GREEN}PR #${pr_number} (${pr_title}) — Copilot review clean, merging and deploying${NC}" >&2
            local merge_action="merge_failed"
            if merge_and_deploy "$pr_number"; then
                resolve_feedback_for_branch "$branch"
                merge_action="merged"
            fi
            local now
            now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
            jq --argjson num "$pr_number" --argjson rid "$review_id" --arg t "$now" --arg a "$merge_action" \
                '.processed_reviews += [{"pr_number": $num, "review_id": $rid, "processed_at": $t, "action": $a}]' \
                "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
            continue
        fi

        log "INFO" "PR #${pr_number} (${pr_title}) has ${comment_count} Copilot review comments — processing"
        echo -e "${GREEN}PR #${pr_number} (${pr_title}) has ${comment_count} Copilot review comments — processing${NC}" >&2

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

        run_claude "$prompt_file" "$output_file" 600 "sonnet"
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
            log "INFO" "PR #${pr_number} (${pr_title}) — safe to merge, merging and deploying"
            echo -e "${GREEN}PR #${pr_number} (${pr_title}) — safe to merge, merging and deploying${NC}" >&2
            if merge_and_deploy "$pr_number"; then
                resolve_feedback_for_branch "$branch"
                action="merged"
            else
                action="merge_failed"
            fi
        else
            log "INFO" "PR #${pr_number} (${pr_title}) — not safe to auto-merge, assigning to jdevalk"
            echo -e "${YELLOW}PR #${pr_number} (${pr_title}) — assigning to jdevalk for review${NC}" >&2
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
        git branch --merged main | grep -E '^\s+(feedback|optimize|fix)/' | xargs -r git branch -d 2>/dev/null
    done 3< <(echo "$prs" | jq -c '.[]')

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
            local last_commit=$(cd "$proj_dir" && git log -1 --format=%H -- "$relative_file" 2>/dev/null)
            local reviewed_commit=$(jq -r --arg f "$tracker_key" '.reviewed_files[$f] // empty' "$tracker")
            if [ "$last_commit" != "$reviewed_commit" ]; then
                target_file="$relative_file"
                target_project="$proj_name"
                target_dir="$proj_dir"
                break 2
            fi
        done <<< "$proj_files"
    done

    if [ -z "$target_file" ]; then
        log "INFO" "All files across all projects have been reviewed — optimization cycle complete"
        echo -e "${GREEN}All files reviewed. Optimization cycle complete.${NC}" >&2
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

    run_claude "$prompt_file" "$output_file" 300 "sonnet"

    echo "$CLAUDE_OUTPUT"

    # Request Copilot review if a PR was created (skip for private repos)
    local created_pr=false
    local opt_pr_url=$(echo "$CLAUDE_OUTPUT" | grep -oE 'https://github.com/[^ ]*pull/[0-9]+' | head -1)
    if [ -n "$opt_pr_url" ]; then
        created_pr=true
        if [ "$target_project" != "website" ]; then
            local opt_pr_number=$(echo "$opt_pr_url" | grep -oE '[0-9]+$')
            if [ -n "$opt_pr_number" ]; then
                log "INFO" "Requesting Copilot review for optimization PR #${opt_pr_number}"
                gh copilot-review "$opt_pr_number" 2>&1 || log "WARN" "Copilot review request failed for PR #${opt_pr_number}"
            fi
        else
            log "INFO" "Skipping Copilot review for private repo: ${target_project}"
        fi
    fi

    # Mark file as reviewed with its current commit hash
    local now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    local tracker_key="${target_project}:${target_file}"
    local file_commit=$(cd "$target_dir" && git log -1 --format=%H -- "$target_file" 2>/dev/null)
    if [ "$created_pr" = true ]; then
        log "INFO" "Optimization created PR for: \"${target_project}:${target_file}\""
        jq --arg f "$tracker_key" --arg c "$file_commit" --arg t "$now" --arg d "$today" \
            '.reviewed_files[$f] = $c | .last_run = $t | .daily_runs[$d] = ((.daily_runs[$d] // 0) + 1) | .daily_prs[$d] = ((.daily_prs[$d] // 0) + 1)' \
            "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
    else
        log "INFO" "No optimizations found for: \"${target_project}:${target_file}\""
        jq --arg f "$tracker_key" --arg c "$file_commit" --arg t "$now" --arg d "$today" \
            '.reviewed_files[$f] = $c | .last_run = $t | .daily_runs[$d] = ((.daily_runs[$d] // 0) + 1)' \
            "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
    fi

    # Return to main in project dir, then back to rondo-club
    cd "$target_dir"
    git checkout main 2>/dev/null
    git branch --merged main | grep -E '^\s+(feedback|optimize|fix)/' | xargs -r git branch -d 2>/dev/null
    cd "$PROJECT_ROOT"

    log "INFO" "Optimization run complete for: \"${target_project}:${target_file}\""
}

# Process PHP errors from production php_errorlog
process_php_errors() {
    log "INFO" "Checking production php_errorlog for PHP errors"
    echo -e "${YELLOW}Checking production php_errorlog for PHP errors...${NC}" >&2

    local tracker="$PROJECT_ROOT/logs/php-errors-tracker.json"
    local daily_limit=5
    local max_attempts=2

    # Initialize tracker if needed
    if [ ! -f "$tracker" ]; then
        echo '{"attempted_errors": {}, "last_run": null, "daily_runs": {}}' > "$tracker"
    fi

    # Check daily limit
    local today=$(date +%Y-%m-%d)
    local today_runs=$(jq -r --arg d "$today" '.daily_runs[$d] // 0' "$tracker")

    if [ "$today_runs" -ge "$daily_limit" ]; then
        log "INFO" "Daily PHP error fix limit reached ($today_runs/$daily_limit) — skipping"
        echo -e "${GREEN}Daily PHP error fix limit reached ($today_runs/$daily_limit). Skipping.${NC}" >&2
        return 0
    fi

    # Validate SSH variables
    if [ -z "$DEPLOY_SSH_HOST" ] || [ -z "$DEPLOY_SSH_USER" ]; then
        log "WARN" "SSH credentials not configured — skipping PHP error check"
        return 0
    fi

    local ssh_port="${DEPLOY_SSH_PORT:-18765}"
    local remote_debug_log="${DEPLOY_REMOTE_WP_PATH}/php_errorlog"

    # Check if php_errorlog exists on server
    if ! ssh -p "$ssh_port" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "test -f $remote_debug_log" 2>/dev/null; then
        log "INFO" "No php_errorlog found on production server"
        echo -e "${GREEN}No php_errorlog on production — no errors to fix.${NC}" >&2
        return 0
    fi

    # Fetch php_errorlog via SSH pipe (no temp file on server)
    local raw_log
    raw_log=$(ssh -p "$ssh_port" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cat $remote_debug_log" 2>/dev/null)
    if [ $? -ne 0 ] || [ -z "$raw_log" ]; then
        log "WARN" "Failed to fetch php_errorlog or log is empty"
        return 0
    fi

    # Calculate cutoff time (2 hours ago)
    local cutoff_time
    cutoff_time=$(date -v-2H +%s 2>/dev/null || date -d '2 hours ago' +%s)

    # Filter for rondo-club theme errors, recent only
    # WordPress php_errorlog format: [DD-Mon-YYYY HH:MM:SS UTC] PHP Type: message in /path on line N
    local errors_raw
    errors_raw=$(echo "$raw_log" | grep -E "themes/rondo-club" | grep -E "PHP (Fatal error|Parse error|Warning|Notice|Deprecated)" || true)

    if [ -z "$errors_raw" ]; then
        log "INFO" "No rondo-club PHP errors in php_errorlog"
        echo -e "${GREEN}No PHP errors for rondo-club theme.${NC}" >&2
        return 0
    fi

    # Filter to recent errors and deduplicate by signature (file:line:type)
    local new_errors=""
    local new_error_count=0
    local seen_sigs=""

    while IFS= read -r line; do
        [ -z "$line" ] && continue

        # Check if recent (within last 2 hours)
        local is_recent=false
        if [[ $line =~ \[([0-9]{2})-([A-Za-z]{3})-([0-9]{4})\ ([0-9]{2}):([0-9]{2}):([0-9]{2})\ UTC\] ]]; then
            local day="${BASH_REMATCH[1]}"
            local month="${BASH_REMATCH[2]}"
            local year="${BASH_REMATCH[3]}"
            local hour="${BASH_REMATCH[4]}"
            local min="${BASH_REMATCH[5]}"
            local sec="${BASH_REMATCH[6]}"
            local log_epoch
            log_epoch=$(date -j -f "%d-%b-%Y %H:%M:%S" "$day-$month-$year $hour:$min:$sec" +%s 2>/dev/null)
            if [ -z "$log_epoch" ]; then
                log_epoch=$(date -d "$day $month $year $hour:$min:$sec UTC" +%s 2>/dev/null)
            fi
            if [ -n "$log_epoch" ] && [ "$log_epoch" -ge "$cutoff_time" ]; then
                is_recent=true
            fi
        fi

        [ "$is_recent" = false ] && continue

        # Extract signature components
        if [[ $line =~ PHP\ (Fatal\ error|Parse\ error|Warning|Notice|Deprecated):\ (.+)\ in\ (.+)\ on\ line\ ([0-9]+) ]]; then
            local error_type="${BASH_REMATCH[1]}"
            local message="${BASH_REMATCH[2]}"
            local file="${BASH_REMATCH[3]}"
            local line_num="${BASH_REMATCH[4]}"

            # Extract relative path from theme root
            local relative_file="${file##*themes/rondo-club/}"
            local signature="${relative_file}:${line_num}:${error_type}"

            # Skip if already seen in this batch
            if echo "$seen_sigs" | grep -qF "$signature" 2>/dev/null; then
                continue
            fi
            seen_sigs="${seen_sigs}${signature}\n"

            # Check tracker: skip if already attempted too many times
            local attempts=$(jq -r --arg s "$signature" '.attempted_errors[$s].attempts // 0' "$tracker")
            if [ "$attempts" -ge "$max_attempts" ]; then
                log "INFO" "Skipping already-attempted error ($attempts tries): $signature"
                continue
            fi

            # Skip if there's an open PR for this error
            local tracked_action=$(jq -r --arg s "$signature" '.attempted_errors[$s].action // empty' "$tracker")
            if [ "$tracked_action" = "pr_created" ]; then
                local tracked_pr=$(jq -r --arg s "$signature" '.attempted_errors[$s].pr_number // empty' "$tracker")
                if [ -n "$tracked_pr" ]; then
                    local pr_state
                    pr_state=$(gh pr view "$tracked_pr" --repo RondoHQ/rondo-club --json state -q '.state' 2>/dev/null)
                    if [ "$pr_state" = "OPEN" ]; then
                        log "INFO" "Skipping error with open PR #${tracked_pr}: $signature"
                        continue
                    fi
                fi
            fi

            # Convert server path to local path for display
            local local_file="${file/*themes\/rondo-club/$PROJECT_ROOT}"

            new_errors="${new_errors}
### ${error_type}: ${relative_file}:${line_num}
- **File:** \`${relative_file}\`
- **Line:** ${line_num}
- **Type:** ${error_type}
- **Message:** ${message}
"
            new_error_count=$((new_error_count + 1))

            # Store signature for tracker update later
            # We use a temp file to collect signatures being attempted
            echo "$signature" >> "/tmp/rondo-php-error-sigs-$$"
        fi
    done <<< "$errors_raw"

    if [ "$new_error_count" -eq 0 ]; then
        log "INFO" "No new PHP errors to fix (all previously attempted or no recent errors)"
        echo -e "${GREEN}No new PHP errors to fix.${NC}" >&2
        rm -f "/tmp/rondo-php-error-sigs-$$"
        return 0
    fi

    log "INFO" "Found $new_error_count new PHP errors to fix"
    echo -e "${YELLOW}Found $new_error_count new PHP error(s) to fix${NC}" >&2

    # Ensure clean main
    cd "$PROJECT_ROOT"
    if ! ensure_clean_main; then
        rm -f "/tmp/rondo-php-error-sigs-$$"
        return 1
    fi

    # Build the full prompt with errors and agent instructions
    local php_error_prompt_file="$PROJECT_ROOT/.claude/php-error-prompt.md"
    local prompt=""
    if [ -f "$php_error_prompt_file" ]; then
        prompt=$(cat "$php_error_prompt_file")
    else
        prompt="Fix the following PHP errors from the production php_errorlog."
    fi

    prompt="${prompt}

## Errors Found (${new_error_count} unique)
${new_errors}"

    # Run Claude with timeout
    local prompt_file=$(mktemp)
    local output_file=$(mktemp)
    printf '%s' "$prompt" > "$prompt_file"

    run_claude "$prompt_file" "$output_file" 600 "sonnet"

    echo "$CLAUDE_OUTPUT"

    # Update tracker for all attempted signatures
    local now=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    local action="no_fix"
    local pr_number=""

    # Check if a PR was created
    local php_pr_url=$(echo "$CLAUDE_OUTPUT" | grep -oE 'https://github.com/[^ ]*pull/[0-9]+' | head -1)
    if [ -n "$php_pr_url" ]; then
        action="pr_created"
        pr_number=$(echo "$php_pr_url" | grep -oE '[0-9]+$')
        log "INFO" "PHP error fix PR created: $php_pr_url"

        # Request Copilot review
        if [ -n "$pr_number" ]; then
            log "INFO" "Requesting Copilot review for PHP error fix PR #${pr_number}"
            gh copilot-review "$pr_number" 2>&1 || log "WARN" "Copilot review request failed for PR #${pr_number}"
        fi
    elif echo "$CLAUDE_OUTPUT" | grep -qi "STATUS:.*NO_CHANGES"; then
        action="no_fix"
        log "INFO" "No PHP errors needed fixing (false positives or already resolved)"
    fi

    # Update tracker for each attempted signature
    if [ -f "/tmp/rondo-php-error-sigs-$$" ]; then
        while IFS= read -r sig; do
            [ -z "$sig" ] && continue
            local prev_attempts=$(jq -r --arg s "$sig" '.attempted_errors[$s].attempts // 0' "$tracker")
            local first_seen=$(jq -r --arg s "$sig" '.attempted_errors[$s].first_seen // empty' "$tracker")
            [ -z "$first_seen" ] && first_seen="$now"

            local update_json
            if [ -n "$pr_number" ]; then
                update_json=$(jq -n --arg fs "$first_seen" --arg ls "$now" --argjson a "$((prev_attempts + 1))" \
                    --arg la "$now" --arg act "$action" --argjson pr "$pr_number" \
                    '{first_seen: $fs, last_seen: $ls, attempts: $a, last_attempt: $la, action: $act, pr_number: $pr}')
            else
                update_json=$(jq -n --arg fs "$first_seen" --arg ls "$now" --argjson a "$((prev_attempts + 1))" \
                    --arg la "$now" --arg act "$action" \
                    '{first_seen: $fs, last_seen: $ls, attempts: $a, last_attempt: $la, action: $act}')
            fi

            jq --arg s "$sig" --argjson v "$update_json" '.attempted_errors[$s] = $v' \
                "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"
        done < "/tmp/rondo-php-error-sigs-$$"
        rm -f "/tmp/rondo-php-error-sigs-$$"
    fi

    # Update daily run count and last_run
    jq --arg t "$now" --arg d "$today" \
        '.last_run = $t | .daily_runs[$d] = ((.daily_runs[$d] // 0) + 1)' \
        "$tracker" > "${tracker}.tmp" && mv "${tracker}.tmp" "$tracker"

    # Return to main and clean up
    cd "$PROJECT_ROOT"
    git checkout main 2>/dev/null
    git branch --merged main | grep -E '^\s+(feedback|optimize|fix)/' | xargs -r git branch -d 2>/dev/null

    log "INFO" "PHP error processing complete"
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

        # Prioritize finishing in-review items before starting new ones
        process_pr_reviews
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
    filter_desc="status=$STATUS"
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

            # Only run PHP errors and optimize when nothing else was worked on
            if [ "$OPTIMIZE_MODE" = true ] && [ "$local_counter" -eq 0 ]; then
                if [ "$PHP_ERRORS_MODE" = true ]; then
                    process_php_errors
                fi
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

            # Fix PHP errors and run optimization if enabled
            if [ "$OPTIMIZE_MODE" = true ] && [ "$RUN_CLAUDE" = true ]; then
                if [ "$PHP_ERRORS_MODE" = true ]; then
                    process_php_errors
                fi
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

log "INFO" "=== Script finished (PID: $$) ==="
exit
}
