#!/usr/bin/env bash
# =====================================================================
# KỊCH BẢN ĐÓNG GÓI & TRIỂN KHAI PRODUCTION (TS5-04 - Linux / Ubuntu)
# =====================================================================
set -euo pipefail

echo "==> [1/6] Cài đặt thư viện PHP tối ưu cho Production..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [2/6] Chạy cập nhật cấu trúc Cơ sở dữ liệu (Migrations)..."
php artisan migrate --force

echo "==> [3/6] Liên kết thư mục lưu trữ công khai (Storage Link)..."
php artisan storage:link || true

echo "==> [4/6] Đóng gói tài nguyên giao diện Frontend (Vite Build)..."
npm ci && npm run build

echo "==> [5/6] Tối ưu hóa bộ nhớ đệm cấu hình, định tuyến và giao diện..."
php artisan optimize

echo "==> [6/6] Kiểm tra sức khỏe toàn hệ thống (Health Check)..."
php artisan app:production-check

echo "Triển khai phiên bản phát hành thành công!"
