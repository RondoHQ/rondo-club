#!/bin/bash
#
# Rondo Club deployment script.
#
# GitHub Actions passes deployment settings through environment variables and
# deploys a pre-built release directory. Local emergency deployments may still
# use .env and build from the repository checkout.
#
# Usage:
#   bin/deploy.sh
#   bin/deploy.sh --source-dir /path/to/release --skip-build --prune
#   bin/deploy.sh --help
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

INCLUDE_NODE_MODULES=false
SKIP_BUILD=false
SKIP_CACHE_CLEAR=false
SKIP_HEALTH_CHECK=false
PRUNE_DELETED=false
SOURCE_DIR="$PROJECT_ROOT"

# These directories are fully controlled by a release. Targeted pruning keeps
# deleted code and Composer packages from lingering while preserving production
# files outside these directories.
PRUNE_DIRS=(includes bin src vendor)

while [ "$#" -gt 0 ]; do
    case "$1" in
        --with-node-modules)
            INCLUDE_NODE_MODULES=true
            ;;
        --skip-build)
            SKIP_BUILD=true
            ;;
        --skip-cache)
            SKIP_CACHE_CLEAR=true
            ;;
        --skip-health-check)
            SKIP_HEALTH_CHECK=true
            ;;
        --prune)
            PRUNE_DELETED=true
            ;;
        --source-dir)
            if [ "$#" -lt 2 ]; then
                echo -e "${RED}Error: --source-dir requires a path${NC}"
                exit 1
            fi
            SOURCE_DIR="$2"
            shift
            ;;
        --help|-h)
            echo "Rondo Club Deployment Script"
            echo ""
            echo "Usage: bin/deploy.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --source-dir PATH     Deploy a prepared release directory"
            echo "  --skip-build          Do not run the frontend build"
            echo "  --with-node-modules   Include node_modules (legacy/local only)"
            echo "  --skip-cache          Skip WordPress and SiteGround cache clearing"
            echo "  --skip-health-check   Skip version and HTTP verification"
            echo "  --prune               Delete removed files in: ${PRUNE_DIRS[*]}"
            echo "  --help, -h            Show this help message"
            echo ""
            echo "Required environment variables:"
            echo "  DEPLOY_SSH_HOST"
            echo "  DEPLOY_SSH_PORT"
            echo "  DEPLOY_SSH_USER"
            echo "  DEPLOY_REMOTE_WP_PATH"
            echo "  DEPLOY_REMOTE_THEME_PATH"
            echo ""
            echo "Optional environment variables:"
            echo "  DEPLOY_PRODUCTION_URL"
            echo "  DEPLOY_SSH_IDENTITY_FILE"
            echo "  DEPLOY_SSH_KNOWN_HOSTS_FILE"
            echo "  DEPLOY_COMMIT_SHA"
            echo ""
            echo "When required variables are not already exported, .env is used"
            echo "as a local fallback."
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information."
            exit 1
            ;;
    esac
    shift
done

if [ ! -d "$SOURCE_DIR" ]; then
    echo -e "${RED}Error: release source does not exist: $SOURCE_DIR${NC}"
    exit 1
fi

required_vars=(
    "DEPLOY_SSH_HOST"
    "DEPLOY_SSH_PORT"
    "DEPLOY_SSH_USER"
    "DEPLOY_REMOTE_WP_PATH"
    "DEPLOY_REMOTE_THEME_PATH"
)

# GitHub Actions exports every required value. Local use falls back to .env.
needs_env=false
for var in "${required_vars[@]}"; do
    if [ -z "${!var:-}" ]; then
        needs_env=true
        break
    fi
done

ENV_FILE="$PROJECT_ROOT/.env"
if [ "$needs_env" = true ] && [ -f "$ENV_FILE" ]; then
    # Wrapper scripts and CI may intentionally override selected .env values.
    # Preserve every deploy variable that was already present before sourcing.
    deploy_vars=(
        "${required_vars[@]}"
        "DEPLOY_PRODUCTION_URL"
        "DEPLOY_SSH_IDENTITY_FILE"
        "DEPLOY_SSH_KNOWN_HOSTS_FILE"
        "DEPLOY_COMMIT_SHA"
    )
    override_names=()
    override_values=()

    for var in "${deploy_vars[@]}"; do
        if [ "${!var+x}" = "x" ]; then
            override_names+=("$var")
            override_values+=("${!var}")
        fi
    done

    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a

    for index in "${!override_names[@]}"; do
        printf -v "${override_names[$index]}" '%s' "${override_values[$index]}"
        export "${override_names[$index]}"
    done
fi

for var in "${required_vars[@]}"; do
    if [ -z "${!var:-}" ]; then
        echo -e "${RED}Error: $var is not set in the environment or .env${NC}"
        exit 1
    fi
done

if [ -n "${DEPLOY_SSH_IDENTITY_FILE:-}" ] && [ ! -f "$DEPLOY_SSH_IDENTITY_FILE" ]; then
    echo -e "${RED}Error: SSH identity file not found${NC}"
    exit 1
fi

if [ -n "${DEPLOY_SSH_KNOWN_HOSTS_FILE:-}" ] && [ ! -f "$DEPLOY_SSH_KNOWN_HOSTS_FILE" ]; then
    echo -e "${RED}Error: SSH known-hosts file not found${NC}"
    exit 1
fi

SSH_OPTIONS=(
    -p "$DEPLOY_SSH_PORT"
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
)

if [ -n "${DEPLOY_SSH_IDENTITY_FILE:-}" ]; then
    SSH_OPTIONS+=(-i "$DEPLOY_SSH_IDENTITY_FILE")
fi

if [ -n "${DEPLOY_SSH_KNOWN_HOSTS_FILE:-}" ]; then
    SSH_OPTIONS+=(-o "UserKnownHostsFile=$DEPLOY_SSH_KNOWN_HOSTS_FILE")
fi

SSH_CMD=(ssh "${SSH_OPTIONS[@]}")
printf -v RSYNC_SSH '%q ' "${SSH_CMD[@]}"
REMOTE_TARGET="$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST"

echo -e "${GREEN}=== Rondo Club Deployment ===${NC}"
echo "Target: $REMOTE_TARGET"
echo "Theme path: $DEPLOY_REMOTE_THEME_PATH"
echo "Source: $SOURCE_DIR"
if [ -n "${DEPLOY_COMMIT_SHA:-}" ]; then
    echo "Commit: $DEPLOY_COMMIT_SHA"
fi
echo ""

if [ "$SKIP_BUILD" = false ]; then
    echo -e "${YELLOW}Step 1: Building frontend assets...${NC}"
    cd "$SOURCE_DIR"
    npm run build
else
    echo -e "${YELLOW}Step 1: Using pre-built frontend assets...${NC}"
fi

if [ ! -f "$SOURCE_DIR/dist/.vite/manifest.json" ] && [ ! -f "$SOURCE_DIR/dist/manifest.json" ]; then
    echo -e "${RED}Error: no Vite manifest found in release dist/${NC}"
    exit 1
fi

if [ ! -f "$SOURCE_DIR/vendor/autoload.php" ]; then
    echo -e "${RED}Error: release is missing vendor/autoload.php${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 2: Syncing dist/ folder...${NC}"
rsync -avz --delete \
    -e "$RSYNC_SSH" \
    "$SOURCE_DIR/dist/" \
    "$REMOTE_TARGET:$DEPLOY_REMOTE_THEME_PATH/dist/"

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
    --exclude='release'
    --exclude='tests'
)

if [ "$INCLUDE_NODE_MODULES" = false ]; then
    RSYNC_EXCLUDES+=(--exclude='node_modules')
else
    echo "(Including node_modules)"
fi

rsync -avz \
    "${RSYNC_EXCLUDES[@]}" \
    -e "$RSYNC_SSH" \
    "$SOURCE_DIR/" \
    "$REMOTE_TARGET:$DEPLOY_REMOTE_THEME_PATH/"

if [ "$PRUNE_DELETED" = true ]; then
    echo -e "${YELLOW}Step 3b: Pruning deleted files from ${PRUNE_DIRS[*]}...${NC}"
    for dir in "${PRUNE_DIRS[@]}"; do
        if [ ! -d "$SOURCE_DIR/$dir" ]; then
            echo "  (skipping ${dir}/ — not present in release)"
            continue
        fi

        echo "  ${dir}/"
        rsync -az --delete \
            -e "$RSYNC_SSH" \
            "$SOURCE_DIR/$dir/" \
            "$REMOTE_TARGET:$DEPLOY_REMOTE_THEME_PATH/$dir/"
    done
fi

if [ "$SKIP_CACHE_CLEAR" = false ]; then
    echo -e "${YELLOW}Step 4: Clearing caches...${NC}"
    "${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp cache flush && wp sg purge"
fi

if [ -n "${DEPLOY_COMMIT_SHA:-}" ]; then
    echo -e "${YELLOW}Step 5: Recording deployed commit...${NC}"
    "${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp option update rondo_deployed_commit $DEPLOY_COMMIT_SHA --quiet"
fi

if [ "$SKIP_HEALTH_CHECK" = false ]; then
    echo -e "${YELLOW}Step 6: Verifying deployment...${NC}"

    EXPECTED_VERSION="$(awk -F': ' '/^Version:/ { print $2; exit }' "$SOURCE_DIR/style.css" | tr -d '\r')"
    THEME_SLUG="${DEPLOY_REMOTE_THEME_PATH%/}"
    THEME_SLUG="${THEME_SLUG##*/}"
    REMOTE_VERSION="$("${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp theme get $THEME_SLUG --field=version" | tail -n 1 | tr -d '\r')"

    if [ -z "$EXPECTED_VERSION" ] || [ "$REMOTE_VERSION" != "$EXPECTED_VERSION" ]; then
        echo -e "${RED}Error: deployed theme version '$REMOTE_VERSION' does not match '$EXPECTED_VERSION'${NC}"
        exit 1
    fi

    if [ -n "${DEPLOY_PRODUCTION_URL:-}" ]; then
        curl --fail --silent --show-error --location \
            --retry 3 --retry-delay 2 --max-time 30 \
            "$DEPLOY_PRODUCTION_URL" >/dev/null
    fi
fi

echo ""
echo -e "${GREEN}=== Deployment Complete ===${NC}"
if [ -n "${DEPLOY_PRODUCTION_URL:-}" ]; then
    echo "Production URL: $DEPLOY_PRODUCTION_URL"
fi
