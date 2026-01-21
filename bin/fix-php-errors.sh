#!/bin/bash
#
# Caelis PHP Error Fix Script
# Retrieves WordPress debug.log from server and uses Claude to fix Caelis PHP errors.
#
# Usage:
#   bin/fix-php-errors.sh              # Fetch errors and run Claude to fix them
#   bin/fix-php-errors.sh --dry-run    # Show errors without running Claude
#   bin/fix-php-errors.sh --help       # Show help
#

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Lock file to prevent concurrent runs
LOCK_FILE="/tmp/caelis-php-errors.lock"
LOCK_CREATED=false
SCRIPT_COMPLETED=false

# Check if another instance is already running
check_lock() {
    if [ -f "$LOCK_FILE" ]; then
        local pid=$(cat "$LOCK_FILE" 2>/dev/null)
        if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
            log "INFO" "Another session already running (PID: $pid), exiting"
            echo "Another PHP error fix session is already running (PID: $pid)" >&2
            exit 0
        else
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

# Remove lock file on exit
cleanup() {
    local exit_code=$?
    if [ "$LOCK_CREATED" = true ]; then
        if [ "$SCRIPT_COMPLETED" = false ]; then
            local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
            echo "[$timestamp] [ERROR] Script exited unexpectedly (exit code: $exit_code)" >> "$LOG_FILE"
        fi
        rm -f "$LOCK_FILE"
    fi
    # Clean up temp files
    rm -f "$TEMP_LOG" "$TEMP_ERRORS" 2>/dev/null
}

trap cleanup EXIT

# Colors for stderr output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Log files
LOG_FILE="$PROJECT_ROOT/logs/php-error-fixer.log"
FIX_LOG="$PROJECT_ROOT/logs/php-fixes.log"
mkdir -p "$(dirname "$LOG_FILE")"

# Temp files
TEMP_LOG=$(mktemp)
TEMP_ERRORS=$(mktemp)

# Logging function
log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

# Log a fix to the fixes log
log_fix() {
    local error_type="$1"
    local file="$2"
    local line="$3"
    local message="$4"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] FIXED: [$error_type] $file:$line - $message" >> "$FIX_LOG"
}

log "INFO" "=== Script started (PID: $$) ==="

# Load environment variables
ENV_FILE="$PROJECT_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}" >&2
    echo "Copy .env.example to .env and fill in your credentials." >&2
    exit 1
fi

set -a
source "$ENV_FILE"
set +a

# Validate required environment variables
REQUIRED_VARS=(
    "DEPLOY_SSH_HOST"
    "DEPLOY_SSH_USER"
    "DEPLOY_REMOTE_WP_PATH"
)

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo -e "${RED}Error: $var is not set in .env${NC}" >&2
        exit 1
    fi
done

# Default values
SSH_PORT="${DEPLOY_SSH_PORT:-22}"
REMOTE_DEBUG_LOG="${DEPLOY_REMOTE_WP_PATH}/wp-content/debug.log"
DRY_RUN=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            cat << EOF
Caelis PHP Error Fix Script

Usage:
  bin/fix-php-errors.sh              # Fetch errors and run Claude to fix them
  bin/fix-php-errors.sh --dry-run    # Show errors without running Claude
  bin/fix-php-errors.sh --help       # Show help

Environment variables (set in .env):
  DEPLOY_SSH_HOST       - SSH host for server
  DEPLOY_SSH_USER       - SSH username
  DEPLOY_SSH_PORT       - SSH port (default: 22)
  DEPLOY_REMOTE_WP_PATH - Path to WordPress installation
  CLAUDE_PATH           - Path to Claude CLI binary

The script:
  1. Connects to server and fetches wp-content/debug.log
  2. Filters for errors in the Caelis theme
  3. Groups duplicate errors
  4. Runs Claude to fix each unique error
  5. Logs all fixes to logs/php-fixes.log
EOF
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}" >&2
            echo "Use --help for usage information." >&2
            exit 1
            ;;
    esac
done

# Check lock and create if free
check_lock
create_lock

echo -e "${CYAN}=== Caelis PHP Error Fixer ===${NC}" >&2

# Step 1: Fetch debug.log from server
echo -e "${YELLOW}Fetching debug.log from server...${NC}" >&2
log "INFO" "Fetching debug.log from $DEPLOY_SSH_HOST:$REMOTE_DEBUG_LOG"

# Check if debug.log exists on server
if ! ssh -p "$SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "test -f $REMOTE_DEBUG_LOG" 2>/dev/null; then
    echo -e "${GREEN}No debug.log found on server - no errors to fix!${NC}" >&2
    log "INFO" "No debug.log found on server"
    SCRIPT_COMPLETED=true
    exit 0
fi

# Fetch the log
if ! scp -P "$SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$REMOTE_DEBUG_LOG" "$TEMP_LOG" 2>/dev/null; then
    echo -e "${RED}Failed to fetch debug.log from server${NC}" >&2
    log "ERROR" "Failed to fetch debug.log via SCP"
    exit 1
fi

LOG_SIZE=$(wc -c < "$TEMP_LOG" | tr -d ' ')
log "INFO" "Fetched debug.log ($LOG_SIZE bytes)"

# Step 2: Filter for Caelis-related errors from the last hour
echo -e "${YELLOW}Filtering for Caelis theme errors (last hour only)...${NC}" >&2

# Calculate cutoff time (1 hour ago)
CUTOFF_TIME=$(date -v-1H +%s 2>/dev/null || date -d '1 hour ago' +%s)

# Function to check if log timestamp is within the last hour
# WordPress debug.log format: [21-Jan-2026 22:20:01 UTC]
is_recent() {
    local log_line="$1"
    # Extract timestamp from log line
    if [[ $log_line =~ \[([0-9]{2})-([A-Za-z]{3})-([0-9]{4})\ ([0-9]{2}):([0-9]{2}):([0-9]{2})\ UTC\] ]]; then
        local day="${BASH_REMATCH[1]}"
        local month="${BASH_REMATCH[2]}"
        local year="${BASH_REMATCH[3]}"
        local hour="${BASH_REMATCH[4]}"
        local min="${BASH_REMATCH[5]}"
        local sec="${BASH_REMATCH[6]}"

        # Convert to epoch (macOS date format)
        local log_epoch=$(date -j -f "%d-%b-%Y %H:%M:%S" "$day-$month-$year $hour:$min:$sec" +%s 2>/dev/null)

        # Fallback for GNU date
        if [ -z "$log_epoch" ]; then
            log_epoch=$(date -d "$day $month $year $hour:$min:$sec UTC" +%s 2>/dev/null)
        fi

        if [ -n "$log_epoch" ] && [ "$log_epoch" -ge "$CUTOFF_TIME" ]; then
            return 0  # Recent
        fi
    fi
    return 1  # Not recent or couldn't parse
}

# Extract errors related to caelis theme path
# WordPress debug.log format: [DD-Mon-YYYY HH:MM:SS UTC] PHP type: message in /path/file.php on line N
TEMP_ALL_ERRORS=$(mktemp)
grep -E "themes/caelis" "$TEMP_LOG" | \
    grep -E "PHP (Fatal error|Parse error|Warning|Notice|Deprecated)" > "$TEMP_ALL_ERRORS" || true

# Filter to only recent errors
while IFS= read -r line; do
    if is_recent "$line"; then
        echo "$line" >> "$TEMP_ERRORS"
    fi
done < "$TEMP_ALL_ERRORS"
rm -f "$TEMP_ALL_ERRORS"

ERROR_COUNT=$(wc -l < "$TEMP_ERRORS" | tr -d ' ')

if [ "$ERROR_COUNT" -eq 0 ]; then
    echo -e "${GREEN}No Caelis PHP errors found in debug.log${NC}" >&2
    log "INFO" "No Caelis errors found in debug.log"
    SCRIPT_COMPLETED=true
    exit 0
fi

echo -e "${CYAN}Found $ERROR_COUNT error entries in log${NC}" >&2
log "INFO" "Found $ERROR_COUNT error entries"

# Step 3: Deduplicate errors (same file:line:message)
# Use temp file for deduplication (bash 3.x compatible)
UNIQUE_FILE=$(mktemp)
SEEN_FILE=$(mktemp)

while IFS= read -r line; do
    # Extract error type, message, file, and line
    # Format: [timestamp] PHP Type: message in /path on line N
    if [[ $line =~ PHP\ (Fatal\ error|Parse\ error|Warning|Notice|Deprecated):\ (.+)\ in\ (.+)\ on\ line\ ([0-9]+) ]]; then
        error_type="${BASH_REMATCH[1]}"
        message="${BASH_REMATCH[2]}"
        file="${BASH_REMATCH[3]}"
        line_num="${BASH_REMATCH[4]}"

        # Create signature for deduplication
        signature="${file}:${line_num}:${error_type}"

        # Check if we've seen this signature
        if ! grep -qF "$signature" "$SEEN_FILE" 2>/dev/null; then
            echo "$signature" >> "$SEEN_FILE"
            # Store as tab-separated: signature, error_type, message, file, line_num
            printf '%s\t%s\t%s\t%s\t%s\n' "$signature" "$error_type" "$message" "$file" "$line_num" >> "$UNIQUE_FILE"
        fi
    fi
done < "$TEMP_ERRORS"

UNIQUE_COUNT=$(wc -l < "$UNIQUE_FILE" | tr -d ' ')
echo -e "${CYAN}$UNIQUE_COUNT unique errors to process${NC}" >&2
log "INFO" "$UNIQUE_COUNT unique errors after deduplication"

if [ "$UNIQUE_COUNT" -eq 0 ]; then
    echo -e "${GREEN}No parseable errors found${NC}" >&2
    rm -f "$UNIQUE_FILE" "$SEEN_FILE"
    SCRIPT_COMPLETED=true
    exit 0
fi

# Step 4: Display errors (dry-run) or process with Claude
if [ "$DRY_RUN" = true ]; then
    echo "" >&2
    echo -e "${CYAN}=== Errors Found (dry-run mode) ===${NC}" >&2
    echo "" >&2

    while IFS=$'\t' read -r signature error_type message file line_num; do
        # Convert server path to local path
        local_file="${file/*themes\/caelis/$PROJECT_ROOT}"

        echo -e "${YELLOW}[$error_type]${NC} $local_file:$line_num" >&2
        echo "  $message" >&2
        echo "" >&2
    done < "$UNIQUE_FILE"

    echo -e "${CYAN}Run without --dry-run to fix these errors${NC}" >&2
    rm -f "$UNIQUE_FILE" "$SEEN_FILE"
    SCRIPT_COMPLETED=true
    exit 0
fi

# Process each unique error with Claude
CLAUDE_BIN="${CLAUDE_PATH:-claude}"
FIXED_COUNT=0
FAILED_COUNT=0

while IFS=$'\t' read -r signature error_type message file line_num; do
    # Convert server path to local path
    local_file="${file/*themes\/caelis/$PROJECT_ROOT}"

    echo "" >&2
    echo -e "${YELLOW}Processing: [$error_type] ${local_file}:${line_num}${NC}" >&2
    echo -e "  ${message}" >&2

    log "INFO" "Processing error: [$error_type] $local_file:$line_num - $message"

    # Check if file exists locally
    if [ ! -f "$local_file" ]; then
        echo -e "${RED}  File not found locally, skipping${NC}" >&2
        log "WARN" "File not found locally: $local_file"
        continue
    fi

    # Build prompt for Claude
    PROMPT=$(cat << EOF
Fix the following PHP error in the Caelis codebase:

**Error Type:** $error_type
**File:** $local_file
**Line:** $line_num
**Message:** $message

Please:
1. Read the file and understand the context around line $line_num
2. Fix the error
3. If the fix is straightforward, just make the change
4. If the error indicates a deeper issue, investigate and fix properly
5. Run \`npm run build\` if you changed any frontend code
6. After fixing, briefly explain what you changed

Do NOT deploy - I will deploy after all fixes are complete.
EOF
)

    # Run Claude
    CLAUDE_OUTPUT=$("$CLAUDE_BIN" --print --dangerously-skip-permissions <<< "$PROMPT" 2>&1)
    CLAUDE_EXIT=$?

    if [ $CLAUDE_EXIT -eq 0 ]; then
        echo -e "${GREEN}  Fixed!${NC}" >&2
        log "INFO" "Successfully fixed: $local_file:$line_num"
        log_fix "$error_type" "$local_file" "$line_num" "$message"
        ((FIXED_COUNT++))

        # Show brief summary of fix
        # Extract the last paragraph as the explanation
        echo "$CLAUDE_OUTPUT" | tail -20 | head -10
    else
        echo -e "${RED}  Failed to fix (exit code: $CLAUDE_EXIT)${NC}" >&2
        log "ERROR" "Failed to fix: $local_file:$line_num (exit code: $CLAUDE_EXIT)"
        log "DEBUG" "Claude output: $CLAUDE_OUTPUT"
        ((FAILED_COUNT++))
    fi
done < "$UNIQUE_FILE"

# Clean up temp files
rm -f "$UNIQUE_FILE" "$SEEN_FILE"

# Summary
echo "" >&2
echo -e "${CYAN}=== Summary ===${NC}" >&2
echo -e "  Fixed: ${GREEN}$FIXED_COUNT${NC}" >&2
echo -e "  Failed: ${RED}$FAILED_COUNT${NC}" >&2
echo -e "  Fix log: $FIX_LOG" >&2

log "INFO" "Summary: Fixed=$FIXED_COUNT, Failed=$FAILED_COUNT"

# Step 5: Clear the server debug.log if we fixed everything
if [ "$FIXED_COUNT" -gt 0 ] && [ "$FAILED_COUNT" -eq 0 ]; then
    echo "" >&2
    echo -e "${YELLOW}All errors fixed. Clearing server debug.log...${NC}" >&2

    if ssh -p "$SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "echo '' > $REMOTE_DEBUG_LOG" 2>/dev/null; then
        echo -e "${GREEN}Server debug.log cleared${NC}" >&2
        log "INFO" "Cleared server debug.log"
    else
        echo -e "${RED}Failed to clear server debug.log${NC}" >&2
        log "WARN" "Failed to clear server debug.log"
    fi

    echo "" >&2
    echo -e "${YELLOW}Remember to deploy the fixes:${NC}" >&2
    echo -e "  bin/deploy.sh" >&2
fi

SCRIPT_COMPLETED=true
log "INFO" "=== Script finished ==="
