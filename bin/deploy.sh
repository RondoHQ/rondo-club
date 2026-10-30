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

DEPLOY_REMOTE_THEME_PATH="${DEPLOY_REMOTE_THEME_PATH%/}"

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
    # SiteGround drops SSH connections now and then. Fail fast on a dead
    # connection instead of hanging for the TCP default, so retry_remote can
    # get to its next attempt while the run is still useful.
    -o ConnectTimeout=20
    -o ServerAliveInterval=15
    -o ServerAliveCountMax=4
)

if [ -n "${DEPLOY_SSH_IDENTITY_FILE:-}" ]; then
    SSH_OPTIONS+=(-i "$DEPLOY_SSH_IDENTITY_FILE")
fi

if [ -n "${DEPLOY_SSH_KNOWN_HOSTS_FILE:-}" ]; then
    SSH_OPTIONS+=(-o "UserKnownHostsFile=$DEPLOY_SSH_KNOWN_HOSTS_FILE")
fi

SSH_CMD=(ssh "${SSH_OPTIONS[@]}")
REMOTE_TARGET="$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST"

SCP_OPTIONS=(
    -P "$DEPLOY_SSH_PORT"
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
    -o ConnectTimeout=20
    -o ServerAliveInterval=15
    -o ServerAliveCountMax=4
)

if [ -n "${DEPLOY_SSH_IDENTITY_FILE:-}" ]; then
    SCP_OPTIONS+=(-i "$DEPLOY_SSH_IDENTITY_FILE")
fi

if [ -n "${DEPLOY_SSH_KNOWN_HOSTS_FILE:-}" ]; then
    SCP_OPTIONS+=(-o "UserKnownHostsFile=$DEPLOY_SSH_KNOWN_HOSTS_FILE")
fi

SCP_CMD=(scp "${SCP_OPTIONS[@]}")

DEPLOY_RETRIES="${DEPLOY_RETRIES:-3}"
DEPLOY_RETRY_DELAY="${DEPLOY_RETRY_DELAY:-15}"

# Run a remote command, retrying transient transfer and SSH failures.
#
# The host regularly refuses or drops connections ("connect to host ... port
# 18765: Connection timed out"), which fails an otherwise healthy deploy. The
# transfer and WP-CLI operations are idempotent, so a rerun is always safe.
retry_remote() {
    local attempt=1
    local delay="$DEPLOY_RETRY_DELAY"
    local status=0

    while true; do
        status=0
        "$@" || status=$?

        if [ "$status" -eq 0 ]; then
            return 0
        fi

        if [ "$attempt" -ge "$DEPLOY_RETRIES" ]; then
            echo -e "${RED}  Failed after ${attempt} attempt(s) (exit ${status}).${NC}" >&2
            return "$status"
        fi

        echo -e "${YELLOW}  Attempt ${attempt} failed (exit ${status}); retrying in ${delay}s...${NC}" >&2
        sleep "$delay"
        attempt=$((attempt + 1))
        delay=$((delay * 2))
    done
}

# Every upload goes to an inactive release. The Python helper validates and
# switches the theme symlink with one filesystem operation, including bootstrap.
LOCAL_ARCHIVE="$(mktemp "${TMPDIR:-/tmp}/rondo-release-XXXXXX")"
RELEASE_ID="${DEPLOY_COMMIT_SHA:-manual}-$(date -u +%Y%m%dT%H%M%S)-${LOCAL_ARCHIVE##*-}"
REMOTE_WP="$(retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" "cd $DEPLOY_REMOTE_WP_PATH && pwd -P")"
# Keep release backups, certificates and deployment tools outside the web root.
RELEASE_ROOT="${REMOTE_WP%/*}/.rondo-releases/${DEPLOY_REMOTE_THEME_PATH##*/}"
LIVE_THEME="$REMOTE_WP/wp-content/themes/${DEPLOY_REMOTE_THEME_PATH##*/}"
printf -v ROOT_COMMAND 'mkdir -p %q' "$RELEASE_ROOT"
retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" "$ROOT_COMMAND"
REMOTE_HELPER="$RELEASE_ROOT/deploy-$RELEASE_ID.py"
REMOTE_ARCHIVE="$RELEASE_ROOT/upload-$RELEASE_ID.tar.gz"

remote_release() {
    local command
    printf -v command 'python3 %q %q %q %q %q' "$REMOTE_HELPER" "$1" "$LIVE_THEME" "$RELEASE_ROOT" "$RELEASE_ID"
    shift
    local argument
    for argument in "$@"; do
        printf -v command '%s %q' "$command" "$argument"
    done
    retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" "$command"
}

# Also runs when SSH loses its reply after activation: rollback reads the durable
# transaction and checks the live target before changing anything.
DEPLOY_FINISHED=false
HELPER_UPLOADED=false
cleanup_deployment() {
    local status=$?
    trap - EXIT
    rm -f "$LOCAL_ARCHIVE"
    if [ "$DEPLOY_FINISHED" = false ] && [ "$HELPER_UPLOADED" = true ]; then
        echo "Deployment failed; restoring the previous release if activated." >&2
        if remote_release rollback; then
            retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" \
                "cd $DEPLOY_REMOTE_WP_PATH && wp rewrite flush --hard && wp cache flush && wp sg purge" || true
        else
            echo "Automatic rollback could not be confirmed; inspect the deployment transaction." >&2
        fi
    fi
    exit "$status"
}
trap cleanup_deployment EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo -e "${GREEN}=== Rondo Club atomic deployment ===${NC}"
if [ "$SKIP_BUILD" = false ]; then
    (cd "$SOURCE_DIR" && npm run build)
fi
if [ ! -f "$SOURCE_DIR/vendor/autoload.php" ]; then
    echo "Release is missing vendor/autoload.php" >&2
    exit 1
fi
php "$SCRIPT_DIR/retain-assets.php" inventory "$SOURCE_DIR/dist"

TAR_EXCLUDES=(
    --exclude='./.git' --exclude='./.github' --exclude='./.env'
    --exclude='./.claude' --exclude='./.agents' --exclude='./.codex'
    --exclude='./.DS_Store' --exclude='./tests' --exclude='./release'
    --exclude='./.impeccable' --exclude='./.husky' --exclude='./graphify-out'
    --exclude='./PRODUCT.md' --exclude='./DESIGN.md'
    --exclude='./certs' --exclude='./dist/.asset-retention.json*'
)
if [ "$INCLUDE_NODE_MODULES" = false ]; then
    TAR_EXCLUDES+=(--exclude='./node_modules')
fi
tar -czf "$LOCAL_ARCHIVE" "${TAR_EXCLUDES[@]}" -C "$SOURCE_DIR" .
ARCHIVE_SHA="$(shasum -a 256 "$LOCAL_ARCHIVE" | cut -d ' ' -f 1)"
retry_remote "${SCP_CMD[@]}" "$SCRIPT_DIR/atomic-release.py" "$REMOTE_TARGET:$REMOTE_HELPER"
HELPER_UPLOADED=true
retry_remote "${SCP_CMD[@]}" "$LOCAL_ARCHIVE" "$REMOTE_TARGET:$REMOTE_ARCHIVE"
# Use current tooling even when rolling back to a release predating this change.
for helper in retain-assets.php; do
    retry_remote "${SCP_CMD[@]}" "$SCRIPT_DIR/$helper" "$REMOTE_TARGET:$RELEASE_ROOT/$RELEASE_ID-$helper"
done
for loader in rondo-00-release.php rondo-error-monitoring.php; do
    retry_remote "${SCP_CMD[@]}" "$PROJECT_ROOT/mu-plugins/$loader" "$REMOTE_TARGET:$RELEASE_ROOT/$RELEASE_ID-$loader"
done

echo "Preparing and checking the inactive release..."
remote_release prepare "$REMOTE_ARCHIVE" "$ARCHIVE_SHA" "$PRUNE_DELETED"
echo "Activating the complete release..."
remote_release activate

retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" \
    "cd $DEPLOY_REMOTE_WP_PATH && wp rewrite flush --hard"
if [ "$SKIP_CACHE_CLEAR" = false ]; then
    retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp cache flush && wp sg purge"
fi

if [ "$SKIP_HEALTH_CHECK" = false ]; then
    EXPECTED_VERSION="$(awk -F': ' '/^Version:/ { print $2; exit }' "$SOURCE_DIR/style.css" | tr -d '\r')"
    REMOTE_VERSION="$(retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp theme get ${DEPLOY_REMOTE_THEME_PATH##*/} --field=version" | tail -n 1 | tr -d '\r')"
    if [ -z "$EXPECTED_VERSION" ] || [ "$REMOTE_VERSION" != "$EXPECTED_VERSION" ]; then
        echo "Deployed version does not match $EXPECTED_VERSION" >&2
        exit 1
    fi
    if [ -n "${DEPLOY_PRODUCTION_URL:-}" ]; then
        for route in "" "/oauth/.well-known/openid-configuration"; do
            curl --fail --silent --show-error --location \
                --retry 3 --retry-delay 2 --max-time 30 \
                "${DEPLOY_PRODUCTION_URL%/}$route" >/dev/null
        done
        # HTTP success alone can be a stale cached release. Verify the web SAPI.
        curl --fail --silent --show-error --retry 3 --retry-delay 2 --max-time 30 \
            "${DEPLOY_PRODUCTION_URL%/}/wp-json/rondo/v1/version?deploy=$RELEASE_ID" \
            | python3 -c 'import json,sys; assert json.load(sys.stdin)["version"] == sys.argv[1], "Live release version mismatch"' "$EXPECTED_VERSION"
    fi
fi
if [ -n "${DEPLOY_COMMIT_SHA:-}" ]; then
    retry_remote "${SSH_CMD[@]}" "$REMOTE_TARGET" \
        "cd $DEPLOY_REMOTE_WP_PATH && wp option update rondo_deployed_commit $DEPLOY_COMMIT_SHA --quiet"
fi
remote_release finish
DEPLOY_FINISHED=true
echo -e "${GREEN}=== Deployment complete and verified ===${NC}"
