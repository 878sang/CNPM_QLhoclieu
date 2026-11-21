param([int]$Port = 8014, [switch]$Worker)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
Get-ChildItem Env: | Where-Object Name -Match '^MAIL_' | ForEach-Object { Remove-Item -LiteralPath "Env:$($_.Name)" }
if ($Worker) {
    if (!(Test-Path '.env.staging')) { throw 'Hãy khởi tạo demo trước khi chạy worker.' }
    php artisan queue:work --env=staging --tries=3 --timeout=90
    exit $LASTEXITCODE
}
if (!(Test-Path '.env.staging')) {
    $config = Get-Content '.env.example' -Raw
    $database = (Join-Path $PSScriptRoot 'database/sprint4-staging.sqlite').Replace('\', '/')
    $config = $config.Replace('APP_ENV=local', 'APP_ENV=staging').Replace('APP_DEBUG=true', 'APP_DEBUG=false')
    $config = $config.Replace('http://localhost:8000', "http://127.0.0.1:$Port").Replace('DB_CONNECTION=mysql', 'DB_CONNECTION=sqlite').Replace('DB_DATABASE=qlhl_cnpm', "DB_DATABASE=$database")
    [System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env.staging'), $config)
    php artisan key:generate --env=staging --force
    if ($LASTEXITCODE -ne 0) { throw 'Không tạo được APP_KEY.' }
}
if (!(Test-Path 'database/sprint4-staging.sqlite')) {
    New-Item 'database/sprint4-staging.sqlite' -ItemType File | Out-Null
}
php artisan migrate --env=staging --force
if ($LASTEXITCODE -ne 0) { throw 'Migration thất bại.' }
php artisan db:seed --class=Sprint4DemoSeeder --env=staging --force
if ($LASTEXITCODE -ne 0) { throw 'Không tạo được dữ liệu demo.' }
npm ci --ignore-scripts
if ($LASTEXITCODE -ne 0) { throw 'Cài frontend thất bại.' }
npm run build
if ($LASTEXITCODE -ne 0) { throw 'Build thất bại.' }
Write-Host "Demo: http://127.0.0.1:$Port | hocvien1 / giangvien / admin | mật khẩu: password"
Write-Host 'Email mặc định ghi log. Để thử SMTP: cấu hình Mailpit trong .env.staging, bật NOTIFICATION_EMAIL_ENABLED và chạy ./Start-Sprint4Demo.ps1 -Worker.'
php artisan serve --env=staging --host=127.0.0.1 --port=$Port --no-reload
