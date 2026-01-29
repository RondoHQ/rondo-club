#!/bin/bash
#
# Stadion Deployment Script
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

# Source .env file (using source for proper variable expansion)
set -a
source "$ENV_FILE"
set +a

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
        --help|-h)
            echo "Stadion Deployment Script"
            echo ""
            echo "Usage: bin/deploy.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --with-node-modules  Include node_modules in sync"
            echo "  --skip-cache         Skip cache clearing after deploy"
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

echo -e "${GREEN}=== Stadion Deployment ===${NC}"
echo "Target: $DEPLOY_SSH_USER@$DEPLOY_SSH_HOST"
echo "Theme path: $DEPLOY_REMOTE_THEME_PATH"
echo ""

# Step 1: Sync dist folder with --delete to remove old build artifacts
echo -e "${YELLOW}Step 1: Syncing dist/ folder...${NC}"
rsync -avz --delete \
    -e "ssh -p $DEPLOY_SSH_PORT" \
    "$PROJECT_ROOT/dist/" \
    "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/dist/"

# Step 2: Sync remaining theme files
echo -e "${YELLOW}Step 2: Syncing theme files...${NC}"
if [ "$INCLUDE_NODE_MODULES" = true ]; then
    echo "(Including node_modules)"
    rsync -avz \
        --exclude='.git' \
        --exclude='dist' \
        -e "ssh -p $DEPLOY_SSH_PORT" \
        "$PROJECT_ROOT/" \
        "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/"
else
    rsync -avz \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='dist' \
        -e "ssh -p $DEPLOY_SSH_PORT" \
        "$PROJECT_ROOT/" \
        "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/"
fi

# Step 3: Regenerate composer autoloader (for new PHP classes)
echo -e "${YELLOW}Step 3: Regenerating composer autoloader...${NC}"
$SSH_CMD "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
    "cd $DEPLOY_REMOTE_THEME_PATH && composer dump-autoload -o --quiet"

# Step 4: Clear caches
if [ "$SKIP_CACHE_CLEAR" = false ]; then
    echo -e "${YELLOW}Step 4: Clearing caches...${NC}"
    $SSH_CMD "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp cache flush && wp sg purge"
fi

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
if [ -n "$DEPLOY_PRODUCTION_URL" ]; then
    echo "Production URL: $DEPLOY_PRODUCTION_URL"
fi
