#!/usr/bin/env bash
# Builds dist/lawzeek-crs-calculator-<version>.zip (+ .sha256) from the files the plugin ships.
# Fails if the version numbers in the plugin header, the version constant, readme Stable tag and block.json disagree.
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=lawzeek-crs-calculator
HEADER=$(sed -n 's/^ \* Version: *//p' $SLUG.php | tr -d '[:space:]')
CONST=$(sed -n "s/^define( 'LZCRS_VERSION', '\(.*\)' );/\1/p" $SLUG.php)
STABLE=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')
BLOCK=$(sed -n 's/^ *"version": *"\(.*\)",/\1/p' blocks/crs-calculator/block.json)
for v in "$CONST" "$STABLE" "$BLOCK"; do
  [ "$v" = "$HEADER" ] || { echo "Version mismatch: header=$HEADER constant=$CONST readme=$STABLE block=$BLOCK" >&2; exit 1; }
done
if [ -n "${EXPECTED_VERSION:-}" ] && [ "$EXPECTED_VERSION" != "$HEADER" ]; then
  echo "Tag version $EXPECTED_VERSION does not match plugin version $HEADER" >&2; exit 1
fi
rm -rf dist && mkdir -p "dist/$SLUG"
rsync -a --exclude-from=.distignore ./ "dist/$SLUG/"
(cd dist && zip -rqX "$SLUG-$HEADER.zip" "$SLUG")
(cd dist && shasum -a 256 "$SLUG-$HEADER.zip" > "$SLUG-$HEADER.zip.sha256")
echo "dist/$SLUG-$HEADER.zip"
unzip -Z1 "dist/$SLUG-$HEADER.zip" | sed 's/^/  /'
