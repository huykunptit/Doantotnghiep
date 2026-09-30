#!/usr/bin/env bash
# Fails if lib/features uses AppColors directly or raw Color(0x…) literals.
# Allowed exceptions: Colors.white/black/transparent on media, heroes, video,
# camera and scrims (see design-system/eript-lms/MASTER.md §6.3).
cd "$(dirname "$0")/../lib/features" || exit 2
# course_detail_page: black scrim gradient over the cover image (allowed).
bad=$(grep -rnE "AppColors\.|Color\(0x" . | grep -v "course_detail_page.dart")
if [ -n "$bad" ]; then
  echo "Hardcoded colors found (use context.cs / context.sem):"
  echo "$bad"
  exit 1
fi
echo "OK: no hardcoded colors in lib/features"
