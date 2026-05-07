#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_MAIN="$ROOT_DIR/PKN-backend.php"
BUILDS_DIR="$ROOT_DIR/builds"
DIST_DIR="$ROOT_DIR/.dist"

mkdir -p "$BUILDS_DIR" "$DIST_DIR"

VERSION=$(sed -n 's/^ \* Version: //p' "$PLUGIN_MAIN" | head -n1 | tr -d '\r')
if [[ -z "$VERSION" ]]; then
  echo "Unable to parse plugin version from $PLUGIN_MAIN" >&2
  exit 1
fi

DATE_UTC=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
SAFE_VERSION="$(echo "$VERSION" | tr " /" "-_")"
ZIP_NAME="pkn-backend-${SAFE_VERSION}.zip"
ZIP_PATH="$BUILDS_DIR/$ZIP_NAME"

rm -f "$ZIP_PATH"

rDEST_DIR="$DIST_DIR/pkn-backend"
rm -rf "$DEST_DIR"
mkdir -p "$DEST_DIR"

cp "$PLUGIN_MAIN" "$DEST_DIR/PKN-backend.php"
for folder in templates lang includes assets; do
  if [[ -d "$ROOT_DIR/$folder" ]]; then
    cp -R "$ROOT_DIR/$folder" "$DEST_DIR/$folder"
  else
    echo "WARNING: Folder not found, skipping: $folder" >&2
  fi
done

find "$DEST_DIR" -type d \( -name '.vs' -o -name '.vscode' \) -prune -exec rm -rf {} +
find "$DEST_DIR" -type f \( -name '*.slnx' -o -name '*.suo' -o -name '*.user' -o -name '*.vsidx' \) -delete

(
  cd "$DIST_DIR"
  zip -qr "$ZIP_PATH" "pkn-backend"
)

cat > "$BUILDS_DIR/latest.json" <<JSON
{
  "name": "PKN Backend",
  "slug": "pkn-backend",
  "version": "${VERSION}",
  "built_at": "${DATE_UTC}",
  "package_url": "https://github.com/JIFO0/PKN/raw/main/builds/${ZIP_NAME}",
  "details_url": "https://github.com/JIFO0/PKN",
  "requires_wp": "6.0",
  "requires_php": "7.4",
  "tested": "6.5",
  "description": "Automated build from GitHub builds folder.",
  "changelog": "See commit history for details."
}
JSON

echo "Build created: $ZIP_PATH"
echo "Manifest updated: $BUILDS_DIR/latest.json"
if [[ "${SC_CREATE_GITHUB_RELEASE:-0}" == "1" ]]; then
  if command -v gh >/dev/null 2>&1; then
    TAG="v${VERSION}"
    gh release view "$TAG" >/dev/null 2>&1 || gh release create "$TAG" "$ZIP_PATH" --title "PKN Backend ${VERSION}" --notes "Automated release for ${VERSION}"
    gh release upload "$TAG" "$ZIP_PATH" --clobber
    echo "Release asset uploaded to GitHub release: $TAG"
  else
    echo "SC_CREATE_GITHUB_RELEASE=1 set but gh CLI is not installed."
  fi
fi
