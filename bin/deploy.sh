#!/bin/bash
#
# Rondo Club Deployment Script
# Deploys theme files to production server and clears caches.
#
# Usage:
#   bin/deploy.sh                    # Deploy without node_modules
#   bin/deploy.sh --with-node-modules # Deploy including node_modules
#   bin/deploy.sh --help             # Show this help
#

set -e

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Load environment variables
ENV_FILE="$PROJECT_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: .env file not found at $ENV_FILE${NC}"
    echo "Copy .env.example to .env and fill in your deployment credentials."
    exit 1
fi

# Save any pre-set deploy overrides (from wrapper scripts like deploy-demo.sh)
_SAVE_SSH_USER="${DEPLOY_SSH_USER:-}"
_SAVE_REMOTE_WP_PATH="${DEPLOY_REMOTE_WP_PATH:-}"
_SAVE_REMOTE_THEME_PATH="${DEPLOY_REMOTE_THEME_PATH:-}"
_SAVE_PRODUCTION_URL="${DEPLOY_PRODUCTION_URL:-}"

# Source .env file
set -a
source "$ENV_FILE"
set +a

# Restore any pre-set overrides
[ -n "$_SAVE_SSH_USER" ] && DEPLOY_SSH_USER="$_SAVE_SSH_USER"
[ -n "$_SAVE_REMOTE_WP_PATH" ] && DEPLOY_REMOTE_WP_PATH="$_SAVE_REMOTE_WP_PATH"
[ -n "$_SAVE_REMOTE_THEME_PATH" ] && DEPLOY_REMOTE_THEME_PATH="$_SAVE_REMOTE_THEME_PATH"
[ -n "$_SAVE_PRODUCTION_URL" ] && DEPLOY_PRODUCTION_URL="$_SAVE_PRODUCTION_URL"

# Validate required variables
required_vars=(
    "DEPLOY_SSH_HOST"
    "DEPLOY_SSH_PORT"
    "DEPLOY_SSH_USER"
    "DEPLOY_REMOTE_WP_PATH"
    "DEPLOY_REMOTE_THEME_PATH"
)

for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo -e "${RED}Error: $var is not set in .env${NC}"
        exit 1
    fi
done

# Parse arguments
INCLUDE_NODE_MODULES=false
SKIP_CACHE_CLEAR=false
PRUNE_DELETED=false

# Directories whose entire contents are git-tracked locally. `--prune` runs a
# targeted `rsync --delete` on each of these so files deleted locally (PHP
# classes, scripts, etc.) are also removed from prod. The rest of the theme
# directory stays untouched — prod-only files like acf-json/*.json, logs,
# README.md, php_errorlog etc. are never considered for deletion.
PRUNE_DIRS=(includes bin src)

for arg in "$@"; do
    case $arg in
        --with-node-modules)
            INCLUDE_NODE_MODULES=true
            shift
            ;;
        --skip-cache)
            SKIP_CACHE_CLEAR=true
            shift
            ;;
        --prune)
            PRUNE_DELETED=true
            shift
            ;;
        --help|-h)
            echo "Rondo Club Deployment Script"
            echo ""
            echo "Usage: bin/deploy.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --with-node-modules  Include node_modules in sync"
            echo "  --skip-cache         Skip cache clearing after deploy"
            echo "  --prune              Delete files on prod that have been removed"
            echo "                       locally. Surgical: only applies to fully"
            echo "                       git-tracked directories (${PRUNE_DIRS[*]})."
            echo "                       Use this whenever a deploy removes PHP classes"
            echo "                       or tooling scripts."
            echo "  --help, -h           Show this help message"
            echo ""
            echo "Environment variables (set in .env):"
            echo "  DEPLOY_SSH_HOST          Production server hostname"
            echo "  DEPLOY_SSH_PORT          SSH port (default: 22)"
            echo "  DEPLOY_SSH_USER          SSH username"
            echo "  DEPLOY_REMOTE_WP_PATH    WordPress root on server"
            echo "  DEPLOY_REMOTE_THEME_PATH Theme directory on server"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $arg${NC}"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
done

# Build SSH command
SSH_CMD="ssh -p $DEPLOY_SSH_PORT"
RSYNC_SSH="-e \"ssh -p $DEPLOY_SSH_PORT\""

echo -e "${GREEN}=== Rondo Club Deployment ===${NC}"
echo "Target: $DEPLOY_SSH_USER@$DEPLOY_SSH_HOST"
echo "Theme path: $DEPLOY_REMOTE_THEME_PATH"
echo ""

# Step 1: Build frontend assets
echo -e "${YELLOW}Step 1: Building frontend assets...${NC}"
cd "$PROJECT_ROOT"
npm run build

# Step 2: Sync dist folder with --delete to remove old build artifacts
echo -e "${YELLOW}Step 2: Syncing dist/ folder...${NC}"
rsync -avz --delete \
    -e "ssh -p $DEPLOY_SSH_PORT" \
    "$PROJECT_ROOT/dist/" \
    "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/dist/"

# Step 3: Sync remaining theme files
echo -e "${YELLOW}Step 3: Syncing theme files...${NC}"
RSYNC_EXCLUDES=(
    --exclude='.git'
    --exclude='.github'
    --exclude='.claude'
    --exclude='.agents'
    --exclude='.codex'
    --exclude='.husky'
    --exclude='.env'
    --exclude='.DS_Store'
    --exclude='dist'
    --exclude='graphify-out'
    --exclude='tests'
)

if [ "$INCLUDE_NODE_MODULES" = true ]; then
    echo "(Including node_modules)"
    rsync -avz \
        "${RSYNC_EXCLUDES[@]}" \
        -e "ssh -p $DEPLOY_SSH_PORT" \
        "$PROJECT_ROOT/" \
        "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/"
else
    rsync -avz \
        "${RSYNC_EXCLUDES[@]}" \
        --exclude='node_modules' \
        -e "ssh -p $DEPLOY_SSH_PORT" \
        "$PROJECT_ROOT/" \
        "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/"
fi

# Step 3b: Prune deleted files (opt-in via --prune)
# The main Step 3 rsync doesn't use --delete because prod has legitimate files
# that don't exist locally (acf-json/*.json written by the WP admin, logs,
# README.md, php_errorlog, etc.). When a deploy removes a PHP class or tooling
# script, the deleted file stays orphaned on prod until someone cleans it up
# manually. `--prune` fixes that by running a targeted `rsync --delete` on each
# directory whose contents are fully git-tracked.
if [ "$PRUNE_DELETED" = true ]; then
    echo -e "${YELLOW}Step 3b: Pruning deleted files from ${PRUNE_DIRS[*]}...${NC}"
    for dir in "${PRUNE_DIRS[@]}"; do
        if [ ! -d "$PROJECT_ROOT/$dir" ]; then
            echo "  (skipping ${dir}/ — not present locally)"
            continue
        fi
        echo "  ${dir}/"
        rsync -az --delete \
            -e "ssh -p $DEPLOY_SSH_PORT" \
            "$PROJECT_ROOT/$dir/" \
            "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/$dir/"
    done
fi

# Step 4: Regenerate composer autoloader (for new PHP classes)
echo -e "${YELLOW}Step 4: Regenerating composer autoloader...${NC}"
$SSH_CMD "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
    "cd $DEPLOY_REMOTE_THEME_PATH && composer dump-autoload -o --quiet"

# Step 5: Clear caches
if [ "$SKIP_CACHE_CLEAR" = false ]; then
    echo -e "${YELLOW}Step 5: Clearing caches...${NC}"
    $SSH_CMD "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp cache flush && wp sg purge"
fi

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
if [ -n "$DEPLOY_PRODUCTION_URL" ]; then
    echo "Production URL: $DEPLOY_PRODUCTION_URL"
fi
