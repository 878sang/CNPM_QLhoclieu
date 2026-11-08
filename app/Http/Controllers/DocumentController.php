<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use App\Models\MaterialLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
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
            $data = [...$data, ...$this->fileAttributes($request->file('file'))];
        }

        if (empty($data['title'])) {
            $data['title'] = $data['original_name'] ?? 'Tài liệu';
        }

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
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            $data = [...$data, ...$this->fileAttributes($request->file('file'))];
        }

        $document->update($data);

        if ($request->hasFile('file') && $previousPath && $previousPath !== $document->file_path) {
            Storage::delete($previousPath);
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

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã xóa tài liệu thành công.');
    }

    public function show(Request $request, Course $course, Document $document): Response|RedirectResponse|StreamedResponse
    {
        $this->authorizeAccess($course, $document->allow_view);

        // Record log
        $this->logAction($course, $document, 'view', $request);

        // If it's an external link or YouTube/Drive url without file
        if ($document->url && ! $document->file_path) {
            return redirect()->away($document->url);
        }

        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'Tệp tài liệu không tồn tại.');

        // Stream file inline (for PDF, audio, video, images, etc.)
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

    public function download(Request $request, Course $course, Document $document): StreamedResponse|RedirectResponse
    {
        $this->authorizeAccess($course, $document->allow_download);

        // Record log
        $this->logAction($course, $document, 'download', $request);

        if ($document->url && ! $document->file_path) {
            return redirect()->away($document->url);
        }

        abort_unless($document->file_path && Storage::exists($document->file_path), 404, 'Tệp tài liệu không tồn tại.');

        return Storage::download($document->file_path, $document->original_name ?? basename($document->file_path));
    }

    /**
     * Course managers always pass; students need an active enrollment and the matching flag.
     */
    private function authorizeAccess(Course $course, bool $flagAllows): void
    {
        /** @var User|null $user */
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->can('update', $course)) {
            return;
        }

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
        return [
            'file_path' => $file->store('documents'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }

    private function chaptersWithLessons(Course $course): Collection
    {
        return $course->chapters()->with('lessons')->orderBy('position')->get();
    }
}
