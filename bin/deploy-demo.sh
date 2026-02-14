#!/bin/bash
#
# Rondo Club Demo Deployment Script
# Deploys theme files to demo.rondo.club. Same as deploy.sh but targets the demo site.
#
# Usage:
#   bin/deploy-demo.sh                    # Deploy without node_modules
#   bin/deploy-demo.sh --with-node-modules # Deploy including node_modules
#

# Override demo-specific variables before sourcing the main deploy script
export DEPLOY_SSH_USER="u26-b0fnaayuzqqg"
export DEPLOY_REMOTE_WP_PATH="~/www/demo.rondo.club/public_html"
export DEPLOY_REMOTE_THEME_PATH="~/www/demo.rondo.club/public_html/wp-content/themes/rondo-club"
export DEPLOY_PRODUCTION_URL="https://demo.rondo.club/"

# Run the main deploy script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "$SCRIPT_DIR/deploy.sh" "$@"
