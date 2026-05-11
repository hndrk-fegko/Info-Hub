#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"

if ! command -v git >/dev/null 2>&1; then
    echo "git ist erforderlich." >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "zip ist erforderlich." >&2
    exit 1
fi

cd "${ROOT_DIR}"

CURRENT_BRANCH="$(git branch --show-current 2>/dev/null || true)"
REF_NAME="${CURRENT_BRANCH:-${GITHUB_REF_NAME:-$(git rev-parse --short HEAD)}}"
SAFE_REF="${REF_NAME//\//-}"
SHORT_SHA="${GITHUB_SHA:-$(git rev-parse --short HEAD)}"
SHORT_SHA="${SHORT_SHA:0:7}"
ARTIFACT_BASENAME="info-hub-${SAFE_REF}-${SHORT_SHA}"

STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/info-hub-dist.XXXXXX")"
PACKAGE_DIR="${STAGE_DIR}/${ARTIFACT_BASENAME}"
ARCHIVE_PATH="${DIST_DIR}/${ARTIFACT_BASENAME}.zip"

cleanup() {
    rm -rf "${STAGE_DIR}"
}
trap cleanup EXIT

mkdir -p "${PACKAGE_DIR}" "${DIST_DIR}"

PATHSPECS=(
    "."
    ":(exclude).github/**"
    ":(exclude)dist/**"
    ":(exclude)docs/**"
    ":(exclude)scripts/**"
    ":(exclude)tests/**"
)

while IFS= read -r -d '' relative_path; do
    target_dir="${PACKAGE_DIR}/$(dirname "${relative_path}")"
    mkdir -p "${target_dir}"
    cp -p "${ROOT_DIR}/${relative_path}" "${PACKAGE_DIR}/${relative_path}"
done < <(git ls-files -z -- "${PATHSPECS[@]}")

rm -f "${ARCHIVE_PATH}"
(
    cd "${STAGE_DIR}"
    zip -qr "${ARCHIVE_PATH}" "${ARTIFACT_BASENAME}"
)

echo "Created ${ARCHIVE_PATH}"
echo "artifact_name=$(basename "${ARCHIVE_PATH}")"
echo "artifact_path=${ARCHIVE_PATH}"
