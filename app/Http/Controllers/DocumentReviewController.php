<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectDocumentRequest;
use App\Models\Document;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), [...Document::STATUSES, 'all'], true) ? $request->query('status') : Document::STATUS_PENDING;

        return view('admin.documents.index', [
            'status' => $status,
            'documents' => Document::with(['course.teacher', 'reviewer'])
                ->when($status !== 'all', fn ($query) => $query->where('approval_status', $status))
                ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.trim((string) $request->query('q')).'%'))
                ->oldest()
                ->paginate(10)
                ->withQueryString(),
            'pendingCount' => Document::where('approval_status', Document::STATUS_PENDING)->count(),
            'pendingLessons' => Lesson::with(['chapter.course.teacher', 'documents' => fn ($query) => $query->where('approval_status', Document::STATUS_PENDING)])
                ->where(function ($query): void {
                    $query->where('status', 'pending')
                        ->orWhereHas('documents', fn ($documents) => $documents->where('approval_status', Document::STATUS_PENDING));
                })
                ->whereHas('chapter.course')
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function approve(Document $document): RedirectResponse
    {
        $document->update([
            'approval_status' => Document::STATUS_APPROVED,
            'status' => Document::STATUS_APPROVED,
            'rejection_reason' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', "Đã duyệt bài tập \"{$document->title}\".");
    }

    public function reject(RejectDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update([
            'approval_status' => Document::STATUS_REJECTED,
            'status' => Document::STATUS_REJECTED,
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', "Đã từ chối bài tập \"{$document->title}\".");
    }
}
