<?php

namespace App\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class DocumentConverter
{
    public const CONVERTIBLE_EXTENSIONS = ['doc', 'docx', 'ppt', 'pptx'];

    public function needsConversion(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::CONVERTIBLE_EXTENSIONS, true);
    }

    /**
     * Chuyển tệp Word/PowerPoint đã lưu sang PDF bằng LibreOffice.
     *
     * @return string|null Đường dẫn PDF trong kho lưu trữ, hoặc null nếu chuyển đổi thất bại.
     */
    public function convertToPdf(string $storedPath): ?string
    {
        $outputDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'doc-'.Str::uuid();

        try {
            mkdir($outputDir);
            $profile = 'file:///'.ltrim(str_replace('\\', '/', $outputDir), '/').'/profile';
            $result = Process::timeout(120)->run([
                config('services.libreoffice.binary'),
                '--headless',
                '-env:UserInstallation='.$profile,
                '--convert-to', 'pdf',
                '--outdir', $outputDir,
                Storage::path($storedPath),
            ]);

            $pdf = $outputDir.DIRECTORY_SEPARATOR.pathinfo($storedPath, PATHINFO_FILENAME).'.pdf';

            if ($result->failed() || ! is_file($pdf)) {
                report(new RuntimeException('Chuyển đổi PDF thất bại: '.trim($result->errorOutput())));

                return null;
            }

            $target = 'documents/pdf/'.Str::uuid().'.pdf';
            Storage::put($target, file_get_contents($pdf));

            return $target;
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            $this->removeDirectory($outputDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
