# Sprint 4 staging

## Demo trên Windows

Yêu cầu PHP 8.3 trở lên, Composer, Node.js và npm. Chạy `composer install`, sau đó `./Start-Sprint4Demo.ps1` từ thư mục dự án. Script tạo `.env.staging` và SQLite riêng, không sửa `.env` hiện có. Không dùng tài khoản demo trên môi trường có dữ liệu thật.

URL mặc định: http://127.0.0.1:8014. Tài khoản: `hocvien1`, `giangvien`, `admin`; mật khẩu demo: `password`.

Để nhận email thử nghiệm, chạy Mailpit chỉ trên loopback với SMTP `127.0.0.1:1025`, giao diện `127.0.0.1:8025`. Cấu hình `.env.staging`: `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, `NOTIFICATION_EMAIL_ENABLED=true`, rồi chạy `./Start-Sprint4Demo.ps1 -Worker`. Script loại biến MAIL_* được kế thừa trong tiến trình để cấu hình thử nghiệm được lấy đúng từ `.env.staging`; không sửa biến môi trường máy. Không đặt SMTP thật khi dùng dữ liệu demo. Nếu không bật email, thông báo trong ứng dụng vẫn hoạt động.

## Triển khai Staging từ xa

Cần máy chủ/domain, quyền SSH hoặc tài khoản dịch vụ triển khai và cấu hình database/SMTP. Chưa có thông tin này nên chưa triển khai từ xa.

1. Checkout nhánh Sprint 4; chạy `composer install --no-dev --optimize-autoloader`, `npm ci --ignore-scripts`, `npm run build`.
2. Tạo `.env` riêng trên server: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL` là URL HTTPS của Staging. Cấu hình database riêng, session/cache, `QUEUE_CONNECTION=database`, SMTP thử nghiệm và `NOTIFICATION_EMAIL_ENABLED=true` khi cần thử email.
3. Tạo khóa bằng `php artisan key:generate` chỉ khi khởi tạo mới. Sao lưu database, chạy `php artisan migrate --force`; cấu hình web server trỏ document root đến `public`, cấp quyền ghi `storage` và `bootstrap/cache`.
4. Chạy `php artisan optimize`; cấu hình service/Supervisor cho `php artisan queue:work --tries=3 --timeout=90`. Khởi động lại worker sau mỗi lần phát hành bằng `php artisan queue:restart`.
5. Với Staging demo mới hoàn toàn, chạy `php artisan db:seed --class=Sprint4DemoSeeder --force`. Không chạy seeder này trên production.
6. Kiểm tra đăng nhập, đăng/sửa/xóa/trả lời/ghim bình luận, hộp thư thông báo và email SMTP. Kiểm tra `/up`, `queue:failed`, log và lưu ảnh minh chứng từ URL Staging thật.

Rollback bằng bản phát hành trước và bản sao lưu database tương ứng; không dùng `migrate:fresh` trên database đang sử dụng. File `.env.staging`, database demo và thông tin bí mật bị loại khỏi Git.
