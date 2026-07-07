#!/usr/bin/env bash
#
# Build a WordPress-installable zip for Shuriken Reviews.
#
# Usage:
#   bin/package-plugin.sh [--skip-build] [--output PATH]
#
# Options:
#   --skip-build   Skip npm run build (use when assets are already compiled).
#   --output PATH  Zip output path (default: build/shuriken-reviews.zip).
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="shuriken-reviews"
MAIN_FILE="${ROOT}/${PLUGIN_SLUG}.php"
OUTPUT="${ROOT}/build/${PLUGIN_SLUG}.zip"
SKIP_BUILD=0

usage() {
    sed -n '2,10p' "$0" | sed 's/^# \?//'
    exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-build)
            SKIP_BUILD=1
            shift
            ;;
        --output)
            [[ $# -ge 2 ]] || { echo "Missing value for --output" >&2; exit 1; }
            OUTPUT="$2"
            shift 2
            ;;
        -h|--help)
            usage 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage 1
            ;;
    esac
done

if [[ ! -f "$MAIN_FILE" ]]; then
    echo "Plugin main file not found: $MAIN_FILE" >&2
    exit 1
fi

VERSION="$(
    grep -m1 '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN_FILE" \
        | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' \
        | tr -d '\r'
)"

if [[ -z "$VERSION" ]]; then
    echo "Could not read Version from ${MAIN_FILE}" >&2
    exit 1
fi

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    if ! command -v npm >/dev/null 2>&1; then
        echo "npm is required to build block assets (or pass --skip-build)." >&2
        exit 1
    fi

    echo "Building block editor assets..."
    (
        cd "$ROOT"
        if [[ -f package-lock.json ]]; then
            npm ci
        else
            npm install
        fi
        npm run build
    )
fi

# Compiled block bundles are required at runtime.
REQUIRED_BUILD=(
    "build/shuriken-rating/index.js"
    "build/shuriken-grouped-rating/index.js"
    "build/shuriken-query-sort/index.js"
    "build/shuriken-post-sidebar/index.js"
    "build/shared/ratings-store.js"
    "build/shared/block-helpers.js"
)

for asset in "${REQUIRED_BUILD[@]}"; do
    if [[ ! -f "${ROOT}/${asset}" ]]; then
        echo "Missing compiled asset: ${asset} (run without --skip-build)." >&2
        exit 1
    fi
done

STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

STAGE_PLUGIN="${STAGING}/${PLUGIN_SLUG}"
mkdir -p "$STAGE_PLUGIN"

echo "Staging plugin files (v${VERSION})..."
rsync -a \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='node_modules/' \
    --exclude='docs/' \
    --exclude='tests/' \
    --exclude='bin/' \
    --exclude='build/*.zip' \
    --exclude='.DS_Store' \
    --exclude='*.log' \
    --exclude='webpack.config.js' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='.gitignore' \
    --exclude='*.dist' \
    --exclude='*.yml' \
    --exclude='*.yaml' \
    --exclude='*.neon' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='phpunit.xml' \
    --exclude='phpunit.xml.dist' \
    --exclude='phpcs.xml' \
    --exclude='phpcs.xml.dist' \
    --exclude='phpstan.neon' \
    --exclude='phpstan.neon.dist' \
    --exclude='phpstan-baseline.neon' \
    --exclude='dev-helpers/' \
    --exclude='wporg-assets/' \
    "$ROOT/" "$STAGE_PLUGIN/"

mkdir -p "$(dirname "$OUTPUT")"
rm -f "$OUTPUT"

echo "Creating zip..."
(
    cd "$STAGING"
    zip -X -rq "$OUTPUT" "$PLUGIN_SLUG"
)

echo "Package ready: $OUTPUT (${PLUGIN_SLUG} v${VERSION})"
