<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use App\Models\DocumentProgress;
use App\Models\Lesson;
use App\Models\MaterialLog;
use App\Models\User;
use App\Services\DocumentConversionService;
use App\Services\DocumentConverter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentConverter $converter) {}

    public function adminStore(DocumentRequest $request): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $course = Course::findOrFail($request->input('course_id'));
        $data = $request->safe()->except(['file', 'course_id']);

        if ($request->hasFile('file')) {
            $fileAttrs = $this->fileAttributes($request->file('file'));
            $convertedPdf = app(DocumentConversionService::class)->convertOfficeToPdf(
                $fileAttrs['file_path'],
                $fileAttrs['original_name']
            );
            $this->attachConvertedPdfIfAvailable($fileAttrs, $convertedPdf);
            $data = [...$data, ...$fileAttrs];
        }

        if (empty($data['title'])) {
            $data['title'] = $data['original_name'] ?? 'Tài liệu';
        }

        $defaultStatus = Auth::user()?->hasRole('admin') ? 'approved' : 'pending';
        $this->attachStatusIfAvailable($data, $defaultStatus);

        $document = $course->documents()->create($data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã tải lên học liệu thành công.',
                'document' => $document,
            ]);
        }

        return redirect()->route('admin.documents.index')->with('status', 'Đã tải lên học liệu thành công.');
    }

    public function teacherIndex(Request $request): View
    {
        $teacher = Auth::user();
        abort_unless($teacher?->hasRole('teacher'), 403);

        $courses = Course::where('teacher_id', $teacher->id)->with('chapters.lessons')->orderBy('title')->get();
        $courseIds = $courses->pluck('id');

        $query = Document::whereIn('course_id', $courseIds)
            ->with(['course', 'lesson.chapter'])
            ->withCount([
                'logs as views_count' => fn($q) => $q->where('action', 'view'),
                'logs as downloads_count' => fn($q) => $q->where('action', 'download'),
            ])
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->input('course_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->input('q'));
                $q->where(function ($sub) use ($term): void {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('original_name', 'like', "%{$term}%");
                });
            })
            ->latest();

        return view('teacher.documents.index', [
            'documents' => $query->paginate(15)->withQueryString(),
            'courses' => $courses,
        ]);
    }

    public function teacherStore(DocumentRequest $request): RedirectResponse|JsonResponse
    {
        $teacher = Auth::user();
        abort_unless($teacher?->hasRole('teacher'), 403);

        $course = Course::where('teacher_id', $teacher->id)->findOrFail($request->input('course_id'));
        $data = $request->safe()->except(['file', 'course_id']);

        if ($request->hasFile('file')) {
            $fileAttrs = $this->fileAttributes($request->file('file'));
            $convertedPdf = app(DocumentConversionService::class)->convertOfficeToPdf(
                $fileAttrs['file_path'],
                $fileAttrs['original_name']
            );
            $this->attachConvertedPdfIfAvailable($fileAttrs, $convertedPdf);
            $data = [...$data, ...$fileAttrs];
        }

        if (empty($data['title'])) {
            $data['title'] = $data['original_name'] ?? 'Tài liệu';
        }

        $this->attachStatusIfAvailable($data, 'pending');

        $document = $course->documents()->create($data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã tải lên học liệu thành công! Tài liệu đang chờ Quản trị viên duyệt.',
                'document' => $document,
            ]);
        }

        return redirect()->route('teacher.materials.index')->with('status', 'Đã tải lên học liệu thành công! Tài liệu đang chờ Quản trị viên duyệt.');
    }

    public function index(Request $request, Course $course): View
    {
        $this->authorize('update', $course);

        $query = $course->documents()
            ->with('lesson.chapter')
            ->withCount([
                'logs as views_count' => fn($q) => $q->where('action', 'view'),
                'logs as downloads_count' => fn($q) => $q->where('action', 'download'),
            ])
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('lesson_id'), fn($q) => $q->where('lesson_id', $request->input('lesson_id')))
            ->latest();

        return view('documents.index', [
            'course' => $course,
            'documents' => $query->paginate(10)->withQueryString(),
            'chapters' => $this->chaptersWithLessons($course),
        ]);
    }

    /**
     * Trang quản lý bài tập của giảng viên: mọi tài liệu thuộc các khóa học do họ phụ trách.
     */
    public function manage(Request $request): View
    {
        $courses = Course::where('teacher_id', auth()->id())->orderBy('title')->get();
        $status = $request->query('status');

        return view('documents.manage', [
            'courses' => $courses,
            'documents' => Document::with(['course', 'lesson.chapter'])
                ->whereIn('course_id', $courses->modelKeys())
                ->when($request->filled('course_id'), fn($query) => $query->where('course_id', $request->query('course_id')))
                ->when(in_array($status, Document::STATUSES, true), fn($query) => $query->where('approval_status', $status))
                ->when($request->filled('q'), fn($query) => $query->where('title', 'like', '%' . trim((string) $request->query('q')) . '%'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'counts' => Document::whereIn('course_id', $courses->modelKeys())->selectRaw('approval_status, count(*) as total')->groupBy('approval_status')->pluck('total', 'approval_status'),
        ]);
    }

    public function store(DocumentRequest $request, Course $course): RedirectResponse|JsonResponse
    {
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            $fileAttrs = $this->fileAttributes($request->file('file'));
            $convertedPdf = app(DocumentConversionService::class)->convertOfficeToPdf(
                $fileAttrs['file_path'],
                $fileAttrs['original_name']
            );
            $this->attachConvertedPdfIfAvailable($fileAttrs, $convertedPdf);
            $data = [...$data, ...$fileAttrs];
        }

        if (empty($data['title'])) {
            $data['title'] = $data['original_name'] ?? 'Tài liệu';
        }

        $defaultStatus = Auth::user()?->hasRole('admin') ? 'approved' : 'pending';
        $this->attachStatusIfAvailable($data, $defaultStatus);

        $document = $course->documents()->create($data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã thêm tài liệu thành công.',
                'document' => $document,
            ]);
        }

        $redirectUrl = $request->input('redirect_to') ?: route('courses.documents.index', $course);

        return redirect($redirectUrl)->with('status', 'Đã thêm tài liệu thành công.');
    }

    public function edit(Course $course, Document $document): View
    {
        $this->authorize('update', $course);

        return view('documents.edit', [
            'course' => $course,
            'document' => $document,
            'chapters' => $this->chaptersWithLessons($course),
        ]);
    }

    public function update(DocumentRequest $request, Course $course, Document $document): RedirectResponse
    {
        $previousPaths = array_filter([$document->file_path, $document->pdf_path]);
        $lessonId = $request->input('lesson_id', $document->lesson_id);
        $previousPath = $document->file_path;
        $previousPdf = $document->getAttribute('converted_pdf_path');
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            $fileAttrs = $this->fileAttributes($request->file('file'));
            $convertedPdf = app(DocumentConversionService::class)->convertOfficeToPdf(
                $fileAttrs['file_path'],
                $fileAttrs['original_name']
            );
            $this->attachConvertedPdfIfAvailable($fileAttrs, $convertedPdf);
            $data = [...$data, ...$fileAttrs];
        }

        if (Auth::user()?->hasRole('teacher')) {
            $this->attachStatusIfAvailable($data, 'pending');
            $data['rejection_reason'] = null;
        }

        $document->update($data);

        if ($request->hasFile('file')) {
            Storage::delete($previousPaths);
            if ($previousPath && $previousPath !== $document->file_path) {
                Storage::delete($previousPath);
            }
            if ($previousPdf && $previousPdf !== $document->getAttribute('converted_pdf_path')) {
                Storage::delete($previousPdf);
            }
        }

        return redirect()->route('courses.show', [
            'course' => $course,
            'lesson_id' => $lessonId,
            'tab' => 'content',
        ])->with('status', 'Đã cập nhật tài liệu thành công. Tài liệu đang chờ Admin duyệt.');
    }

    public function destroy(Course $course, Document $document): RedirectResponse
    {
        $this->authorize('update', $course);
        $lessonId = $document->lesson_id;

        $document->delete();
        Storage::delete(array_filter([$document->file_path, $document->pdf_path]));

        if ($document->file_path) {
            Storage::delete($document->file_path);
        }
        if ($document->getAttribute('converted_pdf_path')) {
            Storage::delete($document->getAttribute('converted_pdf_path'));
        }

        return redirect()->route('courses.show', [
            'course' => $course,
            'lesson_id' => $lessonId,
            'tab' => 'content',
        ])->with('status', 'Đã xóa tài liệu thành công.');
    }

    /**
     * Phát tệp xem trực tuyến. Dùng BinaryFileResponse để trình phát video tua được (HTTP Range).
     */
    public function show(Request $request, Course $course, Document $document): Response|RedirectResponse|StreamedResponse|View
    {
        $this->authorizeAccess($course, $document, $document->allow_view);

        // Record log
        $this->logAction($course, $document, 'view', $request);

        if ($user = Auth::user()) {
            if (! $document->isVideo() && ! $user->can('update', $course)) {
                DocumentProgress::markViewed($user, $document);
            }
        }

        // If it's an external link or YouTube/Drive url without file
        if ($document->url && ! $document->file_path) {
            return redirect()->away($document->url);
        }

        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'Tệp tài liệu không tồn tại.');

        if ($document->pdf_path && Storage::exists($document->pdf_path)) {
            return Storage::response(
                $document->pdf_path,
                pathinfo($document->original_name ?? 'document', PATHINFO_FILENAME) . '.pdf',
                ['Content-Type' => 'application/pdf', 'Accept-Ranges' => 'bytes'],
                'inline'
            );
        }

        // If raw file stream is requested (for docx-preview JS fetch or download)
        if ($request->boolean('raw')) {
            return Storage::response(
                $document->file_path,
                $document->original_name ?? basename($document->file_path),
                [
                    'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                    'Accept-Ranges' => 'bytes',
                ],
                'inline'
            );
        }

        // If it's an actual PDF file or video, stream inline directly
        if ($document->isPdf() || $document->isVideo()) {
            if ($document->isVideo() && $request->header('Range')) {
                $size = Storage::size($document->file_path);
                preg_match('/bytes=(\d*)-(\d*)/', $request->header('Range'), $range);
                $start = isset($range[1]) && $range[1] !== '' ? (int) $range[1] : 0;
                $end = isset($range[2]) && $range[2] !== '' ? (int) $range[2] : $size - 1;
                $end = min($end, $size - 1);
                $length = max(0, $end - $start + 1);

                return response()->stream(function () use ($document, $start, $length): void {
                    $handle = fopen(Storage::path($document->file_path), 'rb');
                    fseek($handle, $start);
                    $remaining = $length;
                    while ($remaining > 0 && ! feof($handle)) {
                        $chunk = fread($handle, min(8192, $remaining));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        echo $chunk;
                        $remaining -= strlen($chunk);
                    }
                    fclose($handle);
                }, 206, [
                    'Content-Type' => $document->mime_type ?? 'video/mp4',
                    'Content-Length' => (string) $length,
                    'Content-Range' => "bytes {$start}-{$end}/{$size}",
                    'Accept-Ranges' => 'bytes',
                ]);
            }

            return Storage::response(
                $document->file_path,
                $document->original_name ?? basename($document->file_path),
                [
                    'Content-Type' => $document->mime_type ?? ($document->isPdf() ? 'application/pdf' : 'application/octet-stream'),
                    'Accept-Ranges' => 'bytes',
                ],
                'inline'
            );
        }

        // For Word (.doc, .docx) and PowerPoint (.ppt, .pptx) and other office files:
        // Render the in-browser interactive document viewer with full content!
        $conversionService = app(DocumentConversionService::class);
        $extractedHtml = null;
        $pptSlides = [];

        if ($document->isWord()) {
            $extractedHtml = $conversionService->extractWordContent($document->file_path);
        } elseif ($document->isPowerPoint()) {
            $pptSlides = $conversionService->extractPptContent($document->file_path);
        }

        return view('documents.viewer', [
            'course' => $course,
            'document' => $document,
            'extractedHtml' => $extractedHtml,
            'pptSlides' => $pptSlides,
        ]);
    }

    public function download(Request $request, Course $course, Document $document): StreamedResponse|RedirectResponse
    {
        $this->authorizeAccess($course, $document, $document->allow_download);

        // Record log
        $this->logAction($course, $document, 'download', $request);

        if ($document->url && ! $document->file_path) {
            return redirect()->away($document->url);
        }

        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'Tệp tài liệu không tồn tại.');

        return Storage::download($document->file_path, $document->original_name ?? basename($document->file_path));
    }

    /**
     * Course managers always pass; students need an active enrollment, an approved document and the matching flag.
     * Course managers always pass; students need an active enrollment, document must be approved, and matching flag.
     */
    private function authorizeAccess(Course $course, Document $document, bool $flagAllows): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->can('update', $course)) {
            return;
        }

        abort_unless($flagAllows && $document->isApproved() && $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
        abort_unless($document->isApproved(), 403, 'Tài liệu này đang chờ quản trị viên phê duyệt.');

        abort_unless(
            $user && $flagAllows && $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(),
            403,
            'Bạn không có quyền thực hiện thao tác này.'
        );
    }

    private function logAction(Course $course, Document $document, string $action, Request $request): void
    {
        MaterialLog::create([
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'document_id' => $document->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Lưu tệp, chuyển Word/PowerPoint sang PDF và đặt lại trạng thái duyệt (Admin tải lên thì tự duyệt).
     *
     * @return array<string, mixed>
     */
    private function fileAttributes(UploadedFile $file): array
    {
        $path = $file->store('documents');
        $needsConversion = $this->converter->needsConversion($path);
        $pdfPath = $needsConversion ? $this->converter->convertToPdf($path) : null;
        $isAdmin = auth()->user()->hasRole('admin');
        $ext = strtolower($file->getClientOriginalExtension());
        $knownMimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mkv' => 'video/x-matroska',
            'mov' => 'video/quicktime',
        ];

        $mimeType = $knownMimes[$ext] ?? null;
        if (! $mimeType) {
            try {
                $mimeType = $file->getMimeType();
            } catch (\Throwable) {
                $mimeType = null;
            }
        }
        if (! $mimeType) {
            $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        }

        return [
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'pdf_path' => $pdfPath,
            'conversion_status' => ! $needsConversion ? 'not_needed' : ($pdfPath ? 'converted' : 'failed'),
            'approval_status' => $isAdmin ? Document::STATUS_APPROVED : Document::STATUS_PENDING,
            'rejection_reason' => null,
            'reviewed_by' => $isAdmin ? auth()->id() : null,
            'reviewed_at' => $isAdmin ? now() : null,
        ];
    }

    private function chaptersWithLessons(Course $course): Collection
    {
        return $course->chapters()->with('lessons')->orderBy('position')->get();
    }

    /**
     * Ensure converted_pdf_path column exists in documents table before attaching it.
     *
     * @param  array<string, mixed>  $fileAttrs
     */
    private function attachConvertedPdfIfAvailable(array &$fileAttrs, ?string $convertedPdf): void
    {
        if (! $convertedPdf) {
            return;
        }

        try {
            if (! Schema::hasColumn('documents', 'converted_pdf_path')) {
                Schema::table('documents', function (Blueprint $table): void {
                    $table->string('converted_pdf_path')->nullable()->after('file_path');
                });
            }
        } catch (\Throwable) {
            // Safely ignore if table alteration fails
        }

        if (Schema::hasColumn('documents', 'converted_pdf_path')) {
            $fileAttrs['converted_pdf_path'] = $convertedPdf;
        }
    }

    private function attachStatusIfAvailable(array &$data, string $defaultStatus): void
    {
        try {
            if (! Schema::hasColumn('documents', 'status')) {
                Schema::table('documents', function (Blueprint $table): void {
                    $table->string('status')->default('approved')->after('allow_download')->index();
                    $table->text('rejection_reason')->nullable()->after('status');
                });
            }
        } catch (\Throwable) {
        }

        if (Schema::hasColumn('documents', 'status')) {
            $data['status'] = $defaultStatus;
        }
    }
}
