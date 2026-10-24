#!/usr/bin/env bash
# Build dist/fop-wordpress-chat-<version>.zip with a top-level fop-wordpress-chat/ folder
# holding only the plugin runtime files. The version comes from the fop-chat.php header.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="fop-wordpress-chat"
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "$ROOT/fop-chat.php" | head -n 1)"

if [ -z "$VERSION" ]; then
	echo "Could not read Version from fop-chat.php" >&2
	exit 1
fi

# Runtime files only (allowlist).
FILES="fop-chat.php uninstall.php readme.txt includes"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$SLUG"
for f in $FILES; do
	cp -R "$ROOT/$f" "$STAGE/$SLUG/"
done
find "$STAGE" -name '.DS_Store' -delete

mkdir -p "$ROOT/dist"
OUT="$ROOT/dist/$SLUG-$VERSION.zip"
rm -f "$OUT"
(cd "$STAGE" && zip -qrX "$OUT" "$SLUG")

echo "$OUT"
