#!/usr/bin/env bash
# Normalizes a fetched official logo/favicon into the flat payout-destination
# icon convention: <slug>-64.png, <slug>-128.png, <slug>-256.png.
#
# Usage: normalize-icon.sh <input-image> <slug> [output-dir]
#
# Produces square, transparent-background PNGs at 64/128/256px with a small
# safe-padding margin so marks stay legible at 16-24px UI sizes without
# touching the canvas edge.
set -euo pipefail

INPUT="$1"
SLUG="$2"
OUTDIR="${3:-resources/assets/images/payout-destinations}"

if ! command -v magick >/dev/null 2>&1; then
  echo "error: imagemagick 'magick' binary not found" >&2
  exit 1
fi

mkdir -p "$OUTDIR"

for size in 256 128 64; do
  # Resize to fit within (size * 0.82) to leave safe padding, then pad to a
  # transparent square canvas of exactly `size`.
  inner=$(( size * 82 / 100 ))
  magick "$INPUT" \
    -resize "${inner}x${inner}" \
    -background none \
    -gravity center \
    -extent "${size}x${size}" \
    "PNG32:${OUTDIR}/${SLUG}-${size}.png"
done

echo "wrote ${OUTDIR}/${SLUG}-{64,128,256}.png"
