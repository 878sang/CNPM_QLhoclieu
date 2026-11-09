<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use App\Models\MaterialLog;
use App\Models\User;
use App\Services\DocumentConversionService;
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
    public function adminIndex(Request $request): View
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $courses = Course::with('chapters.lessons')->orderBy('title')->get();

        $query = Document::with(['course.teacher', 'lesson.chapter'])
            ->withCount([
                'logs as views_count' => fn ($q) => $q->where('action', 'view'),
                'logs as downloads_count' => fn ($q) => $q->where('action', 'download'),
            ])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->input('course_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->input('q'));
                $q->where(function ($sub) use ($term): void {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('original_name', 'like', "%{$term}%");
                });
            })
            ->latest();

        return view('admin.documents.index', [
            'documents' => $query->paginate(15)->withQueryString(),
            'courses' => $courses,
        ]);
    }

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
                'logs as views_count' => fn ($q) => $q->where('action', 'view'),
                'logs as downloads_count' => fn ($q) => $q->where('action', 'download'),
            ])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->input('course_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
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

    public function approve(Document $document): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $this->updateStatusSafely($document, 'approved', null);

        return back()->with('status', "Đã duyệt học liệu \"{$document->title}\" thành công.");
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $reason = trim((string) $request->input('reason', 'Nội dung chưa phù hợp.'));
        $this->updateStatusSafely($document, 'rejected', $reason);

        return back()->with('status', "Đã từ chối học liệu \"{$document->title}\".");
    }

    public function index(Request $request, Course $course): View
    {
        $this->authorize('update', $course);

        $query = $course->documents()
            ->with('lesson.chapter')
            ->withCount([
                'logs as views_count' => fn ($q) => $q->where('action', 'view'),
                'logs as downloads_count' => fn ($q) => $q->where('action', 'download'),
            ])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->input('lesson_id')))
            ->latest();

        return view('documents.index', [
            'course' => $course,
            'documents' => $query->paginate(10)->withQueryString(),
            'chapters' => $this->chaptersWithLessons($course),
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

        $document->update($data);

        if ($request->hasFile('file')) {
            if ($previousPath && $previousPath !== $document->file_path) {
                Storage::delete($previousPath);
            }
            if ($previousPdf && $previousPdf !== $document->getAttribute('converted_pdf_path')) {
                Storage::delete($previousPdf);
            }
        }

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã cập nhật tài liệu thành công.');
    }

    public function destroy(Course $course, Document $document): RedirectResponse
    {
        $this->authorize('update', $course);

        $document->delete();

        if ($document->file_path) {
            Storage::delete($document->file_path);
        }
        if ($document->getAttribute('converted_pdf_path')) {
            Storage::delete($document->getAttribute('converted_pdf_path'));
        }

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã xóa tài liệu thành công.');
    }

    public function show(Request $request, Course $course, Document $document): Response|RedirectResponse|StreamedResponse|View
    {
        $this->authorizeAccess($course, $document, $document->allow_view);

        // Record log
        $this->logAction($course, $document, 'view', $request);

        // If it's an external link or YouTube/Drive url without file
        if ($document->url && ! $document->file_path) {
            return redirect()->away($document->url);
        }

        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'Tệp tài liệu không tồn tại.');

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
     * Course managers always pass; students need an active enrollment, document must be approved, and matching flag.
     */
    private function authorizeAccess(Course $course, Document $document, bool $flagAllows): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->can('update', $course)) {
            return;
        }

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
     * @return array{file_path: string, original_name: string, mime_type: string, size: int}
     */
    private function fileAttributes(UploadedFile $file): array
    {
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
            'file_path' => $file->store('documents'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
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

    private function updateStatusSafely(Document $document, string $status, ?string $reason = null): void
    {
        try {
            if (! Schema::hasColumn('documents', 'status')) {
                Schema::table('documents', function (Blueprint $table): void {
                    $table->string('status')->default('approved')->after('allow_download')->index();
                    $table->text('rejection_reason')->nullable()->after('status');
                });
            }
        } catch (\Throwable) {}

        if (Schema::hasColumn('documents', 'status')) {
            $updateData = ['status' => $status];
            if (Schema::hasColumn('documents', 'rejection_reason')) {
                $updateData['rejection_reason'] = $reason;
            }
            $document->update($updateData);
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
        } catch (\Throwable) {}

        if (Schema::hasColumn('documents', 'status')) {
            $data['status'] = $defaultStatus;
        }
    }
}
