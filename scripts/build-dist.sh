#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_ROOT="${ROOT_DIR}/dist"
DIST_DIR="${DIST_ROOT}/watch"
ARCHIVE="${DIST_ROOT}/watch-lolipop.tar.gz"

cd "${ROOT_DIR}"

echo "==> Cleaning dist"
rm -rf "${DIST_ROOT}"
mkdir -p "${DIST_DIR}"

copy_path() {
  local path="$1"
  if [[ -e "${ROOT_DIR}/${path}" ]]; then
    mkdir -p "${DIST_DIR}/$(dirname "${path}")"
    cp -R "${ROOT_DIR}/${path}" "${DIST_DIR}/${path}"
  fi
}

echo "==> Copying application files"
for path in \
  public \
  src \
  config \
  templates \
  .htaccess \
  index.php \
  assets.css \
  watches.php \
  runs.php \
  run.php \
  failures.php \
  diagnostics.php \
  scrape.php \
  cron_scrape.php \
  bootstrap.php \
  composer.json \
  .env.example \
  README.md
do
  copy_path "${path}"
done

if [[ -f "${ROOT_DIR}/composer.lock" ]]; then
  copy_path "composer.lock"
fi

echo "==> Creating writable storage skeleton"
mkdir -p "${DIST_DIR}/storage/logs" "${DIST_DIR}/storage/debug"
touch "${DIST_DIR}/storage/logs/.gitkeep" "${DIST_DIR}/storage/debug/.gitkeep"

install_vendor_from_local_composer() {
  if ! command -v composer >/dev/null 2>&1; then
    return 1
  fi

  echo "==> Installing production vendor with local Composer"
  composer install \
    --working-dir="${DIST_DIR}" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-progress
}

install_vendor_from_docker_image() {
  if ! command -v docker >/dev/null 2>&1; then
    return 1
  fi

  echo "==> Building Docker image to extract vendor"
  docker compose build app >/dev/null
  docker compose up -d app >/dev/null
  docker compose cp app:/app/vendor "${DIST_DIR}/vendor" >/dev/null
  docker compose cp app:/app/composer.lock "${DIST_DIR}/composer.lock" >/dev/null 2>&1 || true
}

if [[ -d "${ROOT_DIR}/vendor" ]]; then
  echo "==> Copying local vendor"
  cp -R "${ROOT_DIR}/vendor" "${DIST_DIR}/vendor"
elif ! install_vendor_from_local_composer; then
  install_vendor_from_docker_image
fi

if [[ ! -f "${DIST_DIR}/vendor/autoload.php" ]]; then
  echo "vendor/autoload.php was not created. Install Composer dependencies before deploying." >&2
  exit 1
fi

echo "==> Removing development-only files from dist"
find "${DIST_DIR}" -name ".DS_Store" -delete
rm -rf \
  "${DIST_DIR}/tests" \
  "${DIST_DIR}/storage/database.sqlite" \
  "${DIST_DIR}/storage/"*.sqlite \
  "${DIST_DIR}/storage/"*.lock \
  "${DIST_DIR}/storage/logs/"*.log \
  "${DIST_DIR}/storage/debug/"*
touch "${DIST_DIR}/storage/logs/.gitkeep" "${DIST_DIR}/storage/debug/.gitkeep"

echo "==> Creating archive"
tar -C "${DIST_ROOT}" -czf "${ARCHIVE}" watch

cat <<EOF

Done.

Deployment directory:
  ${DIST_DIR}

Upload archive:
  ${ARCHIVE}

Upload everything inside this directory, including:
  vendor/
  public/
  .env.example

For https://YOUR_DOMAIN/watch/ deployment:
  upload dist/watch/* to the server's web/watch/ directory

EOF
