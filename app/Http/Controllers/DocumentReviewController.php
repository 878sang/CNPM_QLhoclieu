<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectDocumentRequest;
use App\Models\AppNotification;
use App\Models\Assignment;
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
            'assignments' => Assignment::with(['course.teacher', 'lesson'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status === 'approved' ? 'published' : $status))
                ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.trim((string) $request->query('q')).'%'))
                ->oldest()
                ->paginate(10, ['*'], 'assignments_page')
                ->withQueryString(),
            'pendingAssignmentsCount' => Assignment::where('status', Assignment::STATUS_PENDING)->count(),
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

        if ($document->course?->teacher_id) {
            AppNotification::notifyUsers(
                [$document->course->teacher_id],
                'Học liệu đã được phê duyệt',
                "Tài liệu \"{$document->title}\" trong khóa \"{$document->course->title}\" đã được Quản trị viên phê duyệt.",
                AppNotification::TYPE_APPROVAL,
                auth()->id(),
                $document->course_id,
                route('courses.show', ['course' => $document->course_id, 'lesson_id' => $document->lesson_id, 'tab' => 'content'])
            );
        }

        if ($document->course) {
            $docActionUrl = $document->lesson_id
                ? route('student.courses.learn', [$document->course_id, $document->lesson_id])
                : route('student.courses.show', $document->course_id);

            AppNotification::notifyCourseStudents(
                $document->course,
                "Học liệu mới trong khóa \"{$document->course->title}\"",
                ($document->isVideo() ? 'Video bài giảng' : 'Tài liệu')." \"{$document->title}\" vừa được cập nhật.",
                AppNotification::TYPE_COURSE,
                $document->course->teacher_id ?: auth()->id(),
                $docActionUrl
            );
        }

        return back()->with('status', "Đã duyệt bài tập \"{$document->title}\".");
    }

    public function reject(RejectDocumentRequest $request, Document $document): RedirectResponse
    {
        $reason = $request->validated('rejection_reason');
        $document->update([
            'approval_status' => Document::STATUS_REJECTED,
            'status' => Document::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($document->course?->teacher_id) {
            AppNotification::notifyUsers(
                [$document->course->teacher_id],
                'Học liệu bị từ chối phê duyệt',
                "Tài liệu \"{$document->title}\" bị từ chối. Lý do: {$reason}",
                AppNotification::TYPE_APPROVAL,
                auth()->id(),
                $document->course_id,
                route('courses.show', ['course' => $document->course_id, 'lesson_id' => $document->lesson_id, 'tab' => 'content'])
            );
        }

        return back()->with('status', "Đã từ chối bài tập \"{$document->title}\".");
    }

    public function approveAssignment(Assignment $assignment): RedirectResponse
    {
        $assignment->update([
            'status' => 'published',
            'rejection_reason' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($assignment->course?->teacher_id) {
            AppNotification::notifyUsers(
                [$assignment->course->teacher_id],
                'Bài tập đã được phê duyệt',
                "Bài tập \"{$assignment->title}\" trong khóa \"{$assignment->course->title}\" đã được phê duyệt và xuất bản.",
                AppNotification::TYPE_APPROVAL,
                auth()->id(),
                $assignment->course_id,
                route('courses.assignments.index', $assignment->course_id)
            );
        }

        if ($assignment->course) {
            $deadlineText = $assignment->deadline ? ' Hạn nộp: '.$assignment->deadline->format('d/m/Y H:i').'.' : '';
            AppNotification::notifyCourseStudents(
                $assignment->course,
                "Bài tập mới trong khóa \"{$assignment->course->title}\"",
                "Bài tập \"{$assignment->title}\" vừa được mở.{$deadlineText}",
                AppNotification::TYPE_ASSIGNMENT,
                $assignment->course->teacher_id ?: auth()->id(),
                route('student.assignments.do', [$assignment->course_id, $assignment->id])
            );
        }

        return back()->with('status', "Đã duyệt bài tập \"{$assignment->title}\".");
    }

    public function rejectAssignment(Request $request, Assignment $assignment): RedirectResponse
    {
        $reason = trim((string) $request->input('rejection_reason', 'Nội dung chưa phù hợp.'));
        $assignment->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($assignment->course?->teacher_id) {
            AppNotification::notifyUsers(
                [$assignment->course->teacher_id],
                'Bài tập bị từ chối phê duyệt',
                "Bài tập \"{$assignment->title}\" bị từ chối. Lý do: {$reason}",
                AppNotification::TYPE_APPROVAL,
                auth()->id(),
                $assignment->course_id,
                route('courses.assignments.index', $assignment->course_id)
            );
        }

        return back()->with('status', "Đã từ chối bài tập \"{$assignment->title}\".");
    }
}
