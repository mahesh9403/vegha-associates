#!/usr/bin/env bash
#
# Builds the archives you upload to the live site.
#
# The live server owns the blog: the database, the uploaded images and the pages
# generated on publish exist only there. Nothing here may overwrite them, so both
# archives are built with `git archive`, which emits *tracked files only* -- the
# database and uploaded images are gitignored and therefore cannot leak in even
# if someone leaves a copy lying around the working tree.
#
#   full-install-*.zip   First upload to an empty host. Everything, including the
#                        three seeded articles and the generated pages, so the
#                        site is complete the moment it lands.
#
#   code-update-*.zip    Every upload after that. Excludes the generated pages,
#                        because by then the server's copies are newer than the
#                        repository's and re-uploading ours would drop the
#                        client's articles off the listing.
#
# Usage:  bash deploy/make-release.sh
#
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "Working tree is dirty. Commit or stash first so the release matches a known commit."
  git status --short
  exit 1
fi

STAMP=$(date +%Y%m%d_%H%M%S)
OUT="deploy/dist"
mkdir -p "$OUT"
rm -f "$OUT"/full-install-*.zip "$OUT"/code-update-*.zip

# Never shipped: internal notes and this tooling. Whole directories, so the
# archives do not carry empty tasks/ and deploy/ folders onto the server.
DEV_ONLY=('tasks' 'deploy')
# Rewritten by the server whenever an article is published.
GENERATED=('insights.html' 'rss.xml' 'sitemap.xml' 'insights/*')

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
git archive HEAD | tar -x -C "$TMP"

( cd "$TMP" && for p in "${DEV_ONLY[@]}"; do rm -rf $p; done )

FULL="$OUT/full-install-$STAMP.zip"
( cd "$TMP" && zip -qr "$OLDPWD/$FULL" . -x '.gitignore' )

( cd "$TMP" && for p in "${GENERATED[@]}"; do rm -rf $p; done )

CODE="$OUT/code-update-$STAMP.zip"
( cd "$TMP" && zip -qr "$OLDPWD/$CODE" . -x '.gitignore' )

echo "Built from $(git rev-parse --short HEAD) on $(git rev-parse --abbrev-ref HEAD)"
echo
echo "  $FULL"
echo "      $(unzip -Z1 "$FULL" | grep -vc '/$') files -- first upload to an empty host"
echo "  $CODE"
echo "      $(unzip -Z1 "$CODE" | grep -vc '/$') files -- routine updates, leaves the blog alone"
echo
echo "Checks:"
for z in "$FULL" "$CODE"; do
  if unzip -Z1 "$z" | grep -qE 'blog\.sqlite|assets/img/blog/[^/]'; then
    echo "  FAIL: $(basename "$z") contains live blog content"; exit 1
  fi
  if unzip -Z1 "$z" | grep -qE '^(tasks|deploy)/'; then
    echo "  FAIL: $(basename "$z") contains development-only files"; exit 1
  fi
done
if unzip -Z1 "$CODE" | grep -qE '^(insights\.html|rss\.xml|sitemap\.xml|insights/.+)$'; then
  echo "  FAIL: $(basename "$CODE") contains generated pages; it would revert the client's articles"; exit 1
fi
echo "  no live blog content, no dev files, and the update archive leaves the"
echo "  generated pages alone."
