<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use App\Models\DocumentProgress;
use App\Services\DocumentConverter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentConverter $converter) {}

    public function index(Course $course): View
    {
        $this->authorize('update', $course);

        return view('documents.index', [
            'course' => $course,
            'documents' => $course->documents()->with('lesson.chapter')->latest()->paginate(10),
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
                ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->query('course_id')))
                ->when(in_array($status, Document::STATUSES, true), fn ($query) => $query->where('approval_status', $status))
                ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.trim((string) $request->query('q')).'%'))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'counts' => Document::whereIn('course_id', $courses->modelKeys())->selectRaw('approval_status, count(*) as total')->groupBy('approval_status')->pluck('total', 'approval_status'),
        ]);
    }

    public function store(DocumentRequest $request, Course $course): RedirectResponse
    {
        $course->documents()->create([...$request->safe()->except('file'), ...$this->fileAttributes($request->file('file'))]);

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã thêm tài liệu.');
    }

    public function edit(Course $course, Document $document): View
    {
        $this->authorize('update', $course);

        return view('documents.edit', ['course' => $course, 'document' => $document, 'chapters' => $this->chaptersWithLessons($course)]);
    }

    public function update(DocumentRequest $request, Course $course, Document $document): RedirectResponse
    {
        $previousPaths = array_filter([$document->file_path, $document->pdf_path]);
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            $data = [...$data, ...$this->fileAttributes($request->file('file'))];
        }

        $document->update($data);

        if ($request->hasFile('file')) {
            Storage::delete($previousPaths);
        }

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã cập nhật tài liệu.');
    }

    public function destroy(Course $course, Document $document): RedirectResponse
    {
        $this->authorize('update', $course);

        $document->delete();
        Storage::delete(array_filter([$document->file_path, $document->pdf_path]));

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã xóa tài liệu.');
    }

    /**
     * Phát tệp xem trực tuyến. Dùng BinaryFileResponse để trình phát video tua được (HTTP Range).
     */
    public function show(Course $course, Document $document): BinaryFileResponse
    {
        $this->authorizeAccess($course, $document, $document->allow_view);
        $file = $document->viewableFile();
        $user = auth()->user();

        if (! $document->isVideo() && ! $user->can('update', $course)) {
            DocumentProgress::markViewed($user, $document);
        }

        return response()->file(Storage::path($file['path']), ['Content-Type' => $file['mime']])
            ->setContentDisposition('inline', $file['name'], Str::ascii($file['name']));
    }

    public function download(Course $course, Document $document): StreamedResponse
    {
        $this->authorizeAccess($course, $document, $document->allow_download);

        return Storage::download($document->file_path, $document->original_name);
    }

    /**
     * Course managers always pass; students need an active enrollment, an approved document and the matching flag.
     */
    private function authorizeAccess(Course $course, Document $document, bool $flagAllows): void
    {
        $user = auth()->user();

        if ($user->can('update', $course)) {
            return;
        }

        abort_unless($flagAllows && $document->isApproved() && $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
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

        return [
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
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
}
