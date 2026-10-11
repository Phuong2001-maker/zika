#!/usr/bin/env bash
# Đóng gói bản phát hành để upload lên hosting (02 §8.3 bước 4–5).
#   bash deploy/build-release.sh
# Kết quả: release/zika-release-<ngày giờ>.zip gồm
#   public/  → giải nén vào <DOCROOT> (public_html): index.html, _app/, api/index.php, .htaccess, uploads/.htaccess
#   app/     → giải nén vào <APP_DIR> (NGOÀI public_html): bootstrap.php, src/, cron/, tools/, migrations/, vendor/, storage/
# Biến môi trường (máy Windows không có php/composer trên PATH):
#   PHP="D:/zika-devtools/php/php.exe"  COMPOSER="D:/zika-devtools/php/php.exe D:/zika-devtools/composer.phar"
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
OUT="$ROOT/release"
STAGE="$OUT/stage"
STAMP="$(date +%Y%m%d-%H%M)"
ZIP="$OUT/zika-release-$STAMP.zip"

rm -rf "$STAGE"
mkdir -p "$STAGE/public/api" "$STAGE/public/uploads" "$STAGE/app"

echo "1/4  Build web (SvelteKit SPA tĩnh)…"
(cd "$ROOT/web" && npm ci --no-audit --no-fund && npm run build)
cp -R "$ROOT/web/build/." "$STAGE/public/"
cp "$ROOT/deploy/htaccess-root" "$STAGE/public/.htaccess"
mkdir -p "$STAGE/public/_app/immutable"
cp "$ROOT/deploy/htaccess-immutable" "$STAGE/public/_app/immutable/.htaccess"
cp "$ROOT/deploy/htaccess-uploads" "$STAGE/public/uploads/.htaccess"
cp "$ROOT/api/public/index.php" "$STAGE/public/api/index.php"

echo "2/4  Chép mã PHP (bỏ config.php, storage, vendor dev)…"
(cd "$ROOT/api/app" && tar --exclude='./config.php' --exclude='./storage' --exclude='./vendor' -cf - .) | (cd "$STAGE/app" && tar -xf -)
for d in cache/content cache/stats logs backups tmp; do mkdir -p "$STAGE/app/storage/$d"; done
cp "$ROOT/deploy/htaccess-deny" "$STAGE/app/.htaccess"
cp "$ROOT/deploy/htaccess-deny" "$STAGE/app/storage/.htaccess"

echo "3/4  Composer (không gồm gói dev)…"
# $COMPOSER cố ý không đặt trong ngoặc kép để cho phép dạng “php composer.phar”.
(cd "$STAGE/app" && $COMPOSER install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "4/4  Nén $ZIP…"
rm -f "$ZIP"
if command -v zip >/dev/null 2>&1; then
  (cd "$STAGE" && zip -qr "$ZIP" public app)
else
  "$PHP" -r '
    $src = $argv[1]; $zip = new ZipArchive();
    if ($zip->open($argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "Không tạo được zip\n"); exit(1); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
      $rel = str_replace("\\", "/", substr($f->getPathname(), strlen($src) + 1));
      $f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getPathname(), $rel);
    }
    $zip->close();
  ' "$STAGE" "$ZIP"
fi
rm -rf "$STAGE"
echo "Xong: $ZIP"
echo "Tiếp theo: upload, giải nén public/ vào public_html, app/ vào <APP_DIR>, tạo config.php, chạy tools/install.php (02 §8.3)."
