#!/usr/bin/env bash
# Render the wordpress.org icon and banner PNGs from the SVG sources in this folder.
# The SVGs embed their fonts (Plus Jakarta Sans and Comfortaa, SIL Open Font License),
# so they render correctly in Chrome. librsvg ignores embedded fonts, so use Chrome.
set -e
cd "$(dirname "$0")"
OUT=../../.wordpress-org
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
PROFILE="$(mktemp -d)"
trap 'rm -rf "$PROFILE"' EXIT

render() { # svg out width height scale
  "$CHROME" --headless=new --disable-gpu --hide-scrollbars --no-first-run \
    --user-data-dir="$PROFILE" --default-background-color=00000000 \
    --window-size="$3,$4" --force-device-scale-factor="$5" --virtual-time-budget=3000 \
    --screenshot="$2" "file://$PWD/$1" >/dev/null 2>&1
}

mkdir -p "$OUT"
render banner.svg "$PWD/$OUT/banner-1544x500.png" 1544 500 1
render banner.svg "$PWD/$OUT/banner-772x250.png" 1544 500 0.5
render icon.svg   "$PWD/$OUT/icon-256x256.png"   256 256 1
render icon.svg   "$PWD/$OUT/icon-128x128.png"   256 256 0.5
echo "Rendered to $(cd "$OUT" && pwd)"
