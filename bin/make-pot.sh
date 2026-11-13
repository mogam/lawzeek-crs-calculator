#!/usr/bin/env bash
# Regenerates languages/lawzeek-crs-calculator.pot from the plugin's PHP and block JS (needs GNU gettext).
set -euo pipefail
cd "$(dirname "$0")/.."
POT=languages/lawzeek-crs-calculator.pot
VER=$(sed -n 's/^ \* Version: *//p' lawzeek-crs-calculator.php | tr -d '[:space:]')
FILES=$(find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -not -path './tests/*' -not -path './dist/*' | sort)
# shellcheck disable=SC2086
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e \
  --keyword=esc_attr__ --keyword=esc_attr_e --keyword=_x:1,2c --keyword=_n:1,2 --package-name="Lawzeek CRS Calculator" --package-version="$VER" \
  --msgid-bugs-address="https://wordpress.org/support/plugin/lawzeek-crs-calculator" -o "$POT" $FILES
xgettext --language=JavaScript --from-code=UTF-8 --keyword=__ -j -o "$POT" blocks/crs-calculator/index.js
sed -i.bak -e 's/charset=CHARSET/charset=UTF-8/' -e '/^"Plural-Forms: /d' -e "s/^\"Project-Id-Version: .*/\"Project-Id-Version: Lawzeek CRS Calculator $VER\\\\n\"/" "$POT" && rm -f "$POT.bak"
echo "$POT: $(grep -c '^msgid ' "$POT") strings"
