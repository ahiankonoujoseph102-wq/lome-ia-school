#!/usr/bin/env bash
# Illustrations des formations : resources/illustrations/formations/*.svg -> public/images/formations/*.webp (1600 x 1000).
# Nécessite Chromium (CHROMIUM) et ImageMagick (convert). À relancer seulement si un SVG change.
# (la fenêtre est plus haute que l image : Chromium sans écran en retire une partie ; on recadre à 1600 x 1000.)
set -euo pipefail
cd "$(dirname "$0")/.."
CHROMIUM="${CHROMIUM:-$(ls /opt/pw-browsers/chromium-*/chrome-linux/chrome 2>/dev/null | head -1)}"
QUALITY="${QUALITY:-80}"
TMP="$(mktemp -d)"
mkdir -p public/images/formations
for svg in resources/illustrations/formations/*.svg; do
  name="$(basename "$svg" .svg)"
  "$CHROMIUM" --headless --no-sandbox --disable-gpu --hide-scrollbars --window-size=1600,1200 \
    --force-device-scale-factor=1 --screenshot="$TMP/$name.png" "file://$PWD/$svg" >/dev/null 2>&1
  convert "$TMP/$name.png" -crop 1600x1000+0+0 +repage -strip -define webp:method=6 -quality "$QUALITY" "public/images/formations/$name.webp"
  echo "$name.webp : $(stat -c %s "public/images/formations/$name.webp") octets"
done
rm -rf "$TMP"
