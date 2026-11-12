<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use App\Services\DocumentConverter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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

    public function show(Course $course, Document $document): StreamedResponse
    {
        $this->authorizeAccess($course, $document, $document->allow_view);
        $file = $document->viewableFile();

        return Storage::response($file['path'], $file['name'], ['Content-Type' => $file['mime']], 'inline');
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
