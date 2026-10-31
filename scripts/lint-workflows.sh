#!/usr/bin/env bash
# Non-mutating workflow lint with a version-pinned, checksum-verified actionlint.
set -euo pipefail

VERSION="1.7.12"
SHA256="8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8" # linux_amd64 tarball
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if command -v actionlint >/dev/null 2>&1 && [ "$(actionlint -version | head -1)" = "$VERSION" ]; then
    BIN="actionlint"
else
    [ "$(uname -s)-$(uname -m)" = "Linux-x86_64" ] || { echo "actionlint $VERSION not installed (auto-install supports linux/amd64 only)" >&2; exit 1; }
    TMP="$(mktemp -d)"
    trap 'rm -rf "$TMP"' EXIT
    curl -fsSL -o "$TMP/a.tgz" "https://github.com/rhysd/actionlint/releases/download/v${VERSION}/actionlint_${VERSION}_linux_amd64.tar.gz"
    echo "${SHA256}  $TMP/a.tgz" | sha256sum -c -
    tar -xzf "$TMP/a.tgz" -C "$TMP" actionlint
    BIN="$TMP/actionlint"
fi

"$BIN" "$DIR"/.github/workflows/*.yml
echo "Workflow lint OK"
