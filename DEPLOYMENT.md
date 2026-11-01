# Triển khai staging Ubuntu

Cài Ubuntu 24.04, Nginx, PHP 8.3-FPM, extension Laravel và MySQL. Clone vào `/var/www/qlhoclieu`, chạy `composer install --no-dev --optimize-autoloader`, `npm install && npm run build`. Sao chép `.env.example` thành `.env`, điền `APP_KEY`, `APP_URL`, DB, SMTP và Google OAuth; chạy `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.

```nginx
server { listen 80; server_name staging.example.com; root /var/www/qlhoclieu/public; index index.php;
 location / { try_files $uri $uri/ /index.php?$query_string; }
 location ~ \\.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
 location ~ /\\.(?!well-known).* { deny all; }
}
```

`sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R ug+rwx storage bootstrap/cache`. Cập nhật: `git pull`, cài dependency/build, migrate, cache lại, reload PHP-FPM. Google Cloud Console: tạo OAuth Client ID Web application và thêm `${APP_URL}/auth/google/callback`; không commit bí mật.
