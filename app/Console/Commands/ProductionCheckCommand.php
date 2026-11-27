<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Kiểm tra tính sẵn sàng của toàn hệ thống trước và sau khi triển khai Production (TS5-04)';

    public function handle(): int
    {
        $this->info('=== KIỂM TRA SẴN SÀNG HỆ THỐNG (PRODUCTION HEALTH CHECK) ===');
        $hasFailure = false;

        // 1. Kiểm tra APP_KEY
        if (filled(config('app.key'))) {
            $this->line('[OK] APP_KEY đã được cấu hình.');
        } else {
            $this->error('[FAIL] Thiếu APP_KEY. Hãy chạy: php artisan key:generate');
            $hasFailure = true;
        }

        // 2. Kiểm tra kết nối CSDL và các bảng cốt lõi
        try {
            DB::connection()->getPdo();
            $this->line('[OK] Kết nối Cơ sở dữ liệu thành công ('.config('database.default').').');

            $requiredTables = [
                'users', 'roles', 'subjects', 'courses', 'chapters', 'lessons',
                'documents', 'material_logs', 'progress', 'assignments', 'submissions',
                'video_interactions', 'lesson_interactions', 'app_notifications',
                'ai_tutor_messages', 'saved_items',
            ];

            $missingTables = array_filter($requiredTables, fn (string $t) => ! Schema::hasTable($t));
            if (empty($missingTables)) {
                $this->line('[OK] Đầy đủ '.count($requiredTables).' bảng CSDL nghiệp vụ.');
            } else {
                $this->error('[FAIL] Thiếu các bảng CSDL: '.implode(', ', $missingTables).' (Hãy chạy php artisan migrate --force)');
                $hasFailure = true;
            }
        } catch (\Throwable $e) {
            $this->error('[FAIL] Không kết nối được CSDL: '.$e->getMessage());
            $hasFailure = true;
        }

        // 3. Kiểm tra quyền ghi thư mục lưu trữ
        try {
            $probe = 'healthcheck_'.uniqid().'.tmp';
            Storage::disk('local')->put($probe, 'ok');
            Storage::disk('local')->delete($probe);
            $this->line('[OK] Thư mục storage/app có quyền đọc/ghi hợp lệ.');
        } catch (\Throwable $e) {
            $this->error('[FAIL] Lỗi quyền ghi storage: '.$e->getMessage());
            $hasFailure = true;
        }

        // 4. Kiểm tra tài nguyên Frontend đã build (Vite manifest)
        if (file_exists(public_path('build/manifest.json'))) {
            $this->line('[OK] Tài nguyên giao diện Vite (public/build/manifest.json) đã được đóng gói.');
        } else {
            $this->warn('[WARN] Chưa có public/build/manifest.json (Hệ thống sẽ dùng CDN dự phòng hoặc hãy chạy npm run build).');
        }

        // 5. Kiểm tra cấu hình AI Gemini
        if (filled(config('services.gemini.api_key'))) {
            $this->line('[OK] Trợ lý AI Gemini đã cấu hình khóa API (Model: '.config('services.gemini.model').').');
        } else {
            $this->warn('[WARN] GEMINI_API_KEY chưa cấu hình (Trợ lý AI sẽ ở trạng thái tạm tắt).');
        }

        $this->newLine();
        if ($hasFailure) {
            $this->error('Kết luận: Hệ thống CẦN KHẮC PHỤC các mục [FAIL] trước khi mở Production.');

            return self::FAILURE;
        }

        $this->info('Kết luận: Hệ thống ĐẠT CHUẨN và sẵn sàng vận hành Production!');

        return self::SUCCESS;
    }
}
