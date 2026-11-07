<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Course;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
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
        $previousPath = $document->file_path;
        $data = $request->safe()->except('file');

        if ($request->hasFile('file')) {
            $data = [...$data, ...$this->fileAttributes($request->file('file'))];
        }

        $document->update($data);

        if ($request->hasFile('file')) {
            Storage::delete($previousPath);
        }

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã cập nhật tài liệu.');
    }

    public function destroy(Course $course, Document $document): RedirectResponse
    {
        $this->authorize('update', $course);

        $document->delete();
        Storage::delete($document->file_path);

        return redirect()->route('courses.documents.index', $course)->with('status', 'Đã xóa tài liệu.');
    }

    public function show(Course $course, Document $document): StreamedResponse
    {
        $this->authorizeAccess($course, $document->allow_view);

        return Storage::response($document->file_path, $document->original_name, ['Content-Type' => $document->mime_type], 'inline');
    }

    public function download(Course $course, Document $document): StreamedResponse
    {
        $this->authorizeAccess($course, $document->allow_download);

        return Storage::download($document->file_path, $document->original_name);
    }

    /**
     * Course managers always pass; students need an active enrollment and the matching flag.
     */
    private function authorizeAccess(Course $course, bool $flagAllows): void
    {
        $user = auth()->user();

        if ($user->can('update', $course)) {
            return;
        }

        abort_unless($flagAllows && $course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
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
