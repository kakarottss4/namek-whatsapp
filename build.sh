#!/bin/bash
# Run from wp-plugin/ directory: bash build.sh
# Produces namek-whatsapp.zip — upload this to WordPress.

set -e
cd "$(dirname "$0")"

rm -f namek-whatsapp.zip
zip -r namek-whatsapp.zip namek-whatsapp/ \
  --exclude "*.DS_Store" \
  --exclude "*/__MACOSX/*"

echo "Built: $(pwd)/namek-whatsapp.zip"
