#!/usr/bin/env bash
# Crovly WordPress.org SVN release helper
#
# Usage:
#   ./release.sh              — release current version (reads from readme.txt Stable tag)
#   ./release.sh 1.0.7        — bump to 1.0.7, update files, then release
#
# Env vars (or interactive prompt):
#   CROVLY_SVN_USER  — wordpress.org username
#   CROVLY_SVN_PASS  — wordpress.org SVN password
#
# What it does:
#   1. Updates trunk/ in SVN from this directory
#   2. Copies .wordpress-org/ → SVN assets/
#   3. Creates tags/X.X.X from current trunk
#   4. Commits to wordpress.org SVN

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
SVN_DIR="${SVN_DIR:-$HOME/Desktop/crovly-svn}"
SVN_URL="https://plugins.svn.wordpress.org/crovly"

red()   { printf "\033[31m%s\033[0m\n" "$*"; }
green() { printf "\033[32m%s\033[0m\n" "$*"; }
blue()  { printf "\033[34m%s\033[0m\n" "$*"; }

# Optional: bump version
if [[ "${1:-}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  NEW_VER="$1"
  blue "Bumping version to $NEW_VER..."
  sed -i.bak -E "s/^Stable tag: .*/Stable tag: $NEW_VER/" "$PLUGIN_DIR/readme.txt" && rm "$PLUGIN_DIR/readme.txt.bak"
  sed -i.bak -E "s/^( \* Version: ).*/\1$NEW_VER/" "$PLUGIN_DIR/crovly.php" && rm "$PLUGIN_DIR/crovly.php.bak"
  sed -i.bak -E "s/(define\('CROVLY_VERSION', ').*('\);)/\1$NEW_VER\2/" "$PLUGIN_DIR/crovly.php" && rm "$PLUGIN_DIR/crovly.php.bak"
fi

# Read current version from readme
VERSION=$(grep -E "^Stable tag:" "$PLUGIN_DIR/readme.txt" | sed -E 's/^Stable tag:[[:space:]]*//' | tr -d ' \r\n')
[ -n "$VERSION" ] || { red "ERROR: Stable tag not found in readme.txt"; exit 1; }
blue "Releasing version: $VERSION"

# Credentials
USER="${CROVLY_SVN_USER:-}"
PASS="${CROVLY_SVN_PASS:-}"
[ -n "$USER" ] || read -rp "WP.org username: " USER
[ -n "$PASS" ] || read -rsp "WP.org SVN password: " PASS && echo

# 1. Checkout/update SVN
if [ -d "$SVN_DIR/.svn" ]; then
  blue "Updating SVN checkout at $SVN_DIR..."
  (cd "$SVN_DIR" && svn up --username "$USER" --password "$PASS" --non-interactive --no-auth-cache > /dev/null)
else
  blue "Checking out SVN to $SVN_DIR..."
  mkdir -p "$SVN_DIR"
  svn co --username "$USER" --password "$PASS" --non-interactive --no-auth-cache "$SVN_URL" "$SVN_DIR" > /dev/null
fi

# 2. Sync trunk (overwrite with current plugin files, excluding .wordpress-org/, .git, etc.)
blue "Syncing trunk..."
rsync -a --delete \
  --exclude '.git' --exclude '.gitignore' --exclude '.wordpress-org' --exclude 'release.sh' \
  "$PLUGIN_DIR/" "$SVN_DIR/trunk/"

# 3. Sync assets (banners + icons)
if [ -d "$PLUGIN_DIR/.wordpress-org" ]; then
  blue "Syncing assets..."
  rsync -a --delete "$PLUGIN_DIR/.wordpress-org/" "$SVN_DIR/assets/"
fi

# 4. SVN add new files / remove deleted
cd "$SVN_DIR"
svn add --force trunk assets > /dev/null 2>&1 || true
svn status | awk '$1=="!" {print $2}' | xargs -I{} svn rm {} > /dev/null 2>&1 || true

# 5. Create tag if not exists
if [ -d "tags/$VERSION" ]; then
  red "Tag tags/$VERSION already exists. Skipping tag creation."
else
  blue "Creating tag tags/$VERSION from trunk..."
  svn cp trunk "tags/$VERSION"
fi

# 6. Show what we're about to commit
blue "Pending changes:"
svn status

# 7. Commit
read -rp "Proceed with commit? [y/N] " CONFIRM
if [[ "$CONFIRM" =~ ^[Yy]$ ]]; then
  svn ci -m "Release $VERSION" --username "$USER" --password "$PASS" --non-interactive --no-auth-cache
  green "✅ Released $VERSION to wordpress.org/plugins/crovly/"
else
  red "Aborted. Pending changes left in $SVN_DIR for manual commit."
fi
