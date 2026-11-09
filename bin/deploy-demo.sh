#!/bin/bash
#
# Rondo Club Demo Deployment Script
# Deploys theme files to demo.rondo.club. Same as deploy.sh but targets the demo site.
#
# Usage:
#   bin/deploy-demo.sh                    # Deploy without node_modules
#   bin/deploy-demo.sh --with-node-modules # Deploy including node_modules
#

# Keep the demo target independent of production settings in .env or the shell.
# Export DEPLOY_DEMO_* variables to override these defaults for a rehearsal.
export DEPLOY_SSH_HOST="${DEPLOY_DEMO_SSH_HOST:-81.24.6.19}"
export DEPLOY_SSH_PORT="${DEPLOY_DEMO_SSH_PORT:-22}"
export DEPLOY_SSH_USER="${DEPLOY_DEMO_SSH_USER:-rondo-demo}"
export DEPLOY_REMOTE_WP_PATH="${DEPLOY_DEMO_REMOTE_WP_PATH:-/srv/rondo-demo/public_html}"
export DEPLOY_REMOTE_THEME_PATH="${DEPLOY_DEMO_REMOTE_THEME_PATH:-$DEPLOY_REMOTE_WP_PATH/wp-content/themes/rondo-club}"
export DEPLOY_PRODUCTION_URL="${DEPLOY_DEMO_URL:-https://demo.rondo.club/}"
export DEPLOY_SSH_IDENTITY_FILE="${DEPLOY_DEMO_SSH_IDENTITY_FILE:-}"
export DEPLOY_SSH_KNOWN_HOSTS_FILE="${DEPLOY_DEMO_SSH_KNOWN_HOSTS_FILE:-}"

# Run the main deploy script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "$SCRIPT_DIR/deploy.sh" "$@"
