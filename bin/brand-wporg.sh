#!/usr/bin/env bash
# Renders the WordPress.org listing images from brand/src into .wordpress-org (icon 128/256 + svg, banner 772x250 / 1544x500).
set -euo pipefail
cd "$(dirname "$0")/.."
shot() { npx playwright screenshot --browser=chromium --viewport-size="$2" "file://$PWD/brand/src/$1" ".wordpress-org/$3" >/dev/null; echo ".wordpress-org/$3"; }
shot icon.html 128,128 icon-128x128.png
shot icon.html 256,256 icon-256x256.png
shot banner.html 772,250 banner-772x250.png
shot banner.html 1544,500 banner-1544x500.png
cp brand/source/app-icon.svg .wordpress-org/icon.svg && echo ".wordpress-org/icon.svg"
