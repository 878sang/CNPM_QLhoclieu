# =====================================================================
# KỊCH BẢN ĐÓNG GÓI & TRIỂN KHAI PRODUCTION (TS5-04 - Windows PowerShell)
# =====================================================================
$ErrorActionPreference = "Stop"

Write-Host "==> [1/6] Cài đặt thư viện PHP tối ưu cho Production..." -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host "==> [2/6] Chạy cập nhật cấu trúc Cơ sở dữ liệu (Migrations)..." -ForegroundColor Cyan
php artisan migrate --force

Write-Host "==> [3/6] Liên kết thư mục lưu trữ công khai (Storage Link)..." -ForegroundColor Cyan
php artisan storage:link --force 2>$null

Write-Host "==> [4/6] Đóng gói tài nguyên giao diện Frontend (Vite Build)..." -ForegroundColor Cyan
npm.cmd run build

Write-Host "==> [5/6] Tối ưu hóa bộ nhớ đệm cấu hình, định tuyến và giao diện..." -ForegroundColor Cyan
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-Host "==> [6/6] Kiểm tra sức khỏe toàn hệ thống (Health Check)..." -ForegroundColor Cyan
php artisan app:production-check

Write-Host "`nTriển khai phiên bản phát hành thành công!" -ForegroundColor Green
