<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    // ─── Giáo viên: Xem tất cả bài nộp ────────────────────────────────

    public function index(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->authorizeTeacher($course);

        $submissions = $assignment->submissions()
            ->with('student')
            ->latest('submitted_at')
            ->get();

        return view('assignments.submissions.index', compact('course', 'assignment', 'submissions'));
    }

    // ─── Học viên: Nộp bài ─────────────────────────────────────────────

    public function store(Request $request, Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->checkEnrolled($course);
        abort_if(! $assignment->isPublished(), 403, 'Bài tập chưa được mở.');

        $user = Auth::user();

        // Kiểm tra đã nộp chưa
        if ($assignment->submissions()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Bạn đã nộp bài tập này rồi.');
        }

        // Kiểm tra hạn nộp
        if ($assignment->isPastDeadline()) {
            return back()->with('error', 'Đã quá hạn nộp bài.');
        }

        $data = $request->validate([
            'answers' => 'nullable|array',
            'answers.*' => 'nullable|string|max:2000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,zip|max:20480',
        ]);

        // Upload file bài làm
        $filePath = null;
        $originalName = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("submissions/{$assignment->id}", 'private');
            $originalName = $request->file('file')->getClientOriginalName();
        }

        // Lọc đáp án: bỏ câu trống
        $answers = null;
        if (! empty($data['answers'])) {
            $filtered = array_filter($data['answers'], fn ($v) => $v !== null && $v !== '');
            if (! empty($filtered)) {
                $answers = $filtered;
            }
        }

        if ($assignment->type === 'text' && ! $answers && ! $request->hasFile('file')) {
            return back()->withErrors(['submission' => 'Hãy nhập ít nhất một câu trả lời hoặc tải file bài làm lên.'])->withInput();
        }

        // Chấm điểm tự động nếu có đáp án
        $score = null;
        $maxScore = null;
        if ($assignment->answer_key && $answers) {
            $score = $assignment->autoGrade($answers);
            $maxScore = $assignment->maxScore();
        }

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'answers' => $answers,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'score' => $score,
            'max_score' => $maxScore,
            'submitted_at' => now(),
        ]);

        return redirect()->route('student.assignments.result', [$course, $assignment])
            ->with('status', 'Đã nộp bài thành công!');
    }

    // ─── Học viên: Xem kết quả bài làm ─────────────────────────────────

    public function show(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->checkEnrolled($course);

        $user = Auth::user();
        $submission = $assignment->submissions()
            ->where('user_id', $user->id)
            ->firstOrFail();

        return view('student.assignment-result', compact('course', 'assignment', 'submission'));
    }

    // ─── Giáo viên: Xem chi tiết bài làm của học viên ───────────────────
    public function teacherShow(Course $course, Assignment $assignment, Submission $submission)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        abort_if($submission->assignment_id !== $assignment->id, 404);
        $this->authorizeTeacher($course);

        $submission->load(['student', 'grader']);

        return view('assignments.submissions.show', compact('course', 'assignment', 'submission'));
    }

    // ─── Giáo viên: Chấm điểm thủ công ────────────────────────────────

    public function grade(Request $request, Course $course, Assignment $assignment, Submission $submission)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        abort_if($submission->assignment_id !== $assignment->id, 404);
        $this->authorizeTeacher($course);

        $data = $request->validate([
            'score' => 'required|numeric|min:0|max:'.$assignment->maxScore(),
            'feedback' => 'nullable|string|max:2000',
        ]);

        $submission->update([
            'score' => $data['score'],
            'max_score' => $assignment->maxScore(),
            'feedback' => $data['feedback'] ?? null,
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ]);

        return back()->with('status', 'Đã chấm điểm thành công!');
    }

    // ─── Giáo viên: Download file bài nộp ──────────────────────────────

    public function downloadFile(Course $course, Assignment $assignment, Submission $submission)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        abort_if($submission->assignment_id !== $assignment->id, 404);
        $this->authorizeTeacher($course);
        abort_if(! $submission->file_path, 404, 'Bài nộp này không có file.');

        return Storage::disk('private')->download(
            $submission->file_path,
            $submission->original_name ?: 'bai-nop.pdf'
        );
    }

    // ─── Giáo viên: Xem file bài nộp trực tiếp (inline) ─────────────────
    public function viewFile(Course $course, Assignment $assignment, Submission $submission)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        abort_if($submission->assignment_id !== $assignment->id, 404);
        $this->authorizeTeacher($course);
        abort_if(! $submission->file_path, 404, 'Bài nộp này không có file.');

        $disk = Storage::disk('private');
        abort_unless($disk->exists($submission->file_path), 404);
        $mimeType = $disk->mimeType($submission->file_path) ?: 'application/octet-stream';
        $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png'];

        if (! in_array($mimeType, $inlineTypes, true)) {
            return $disk->download($submission->file_path, $submission->original_name ?: 'bai-nop');
        }

        return $disk->response($submission->file_path, $submission->original_name ?: 'bai-nop', [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($submission->original_name ?: 'bai-nop').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ─── Giáo viên: Download file đề bài ───────────────────────────────

    public function downloadAssignmentFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $user = Auth::user();

        // Học viên download đề bài
        if ($user->hasRole('student')) {
            $this->checkEnrolled($course);
            abort_if(! $assignment->isPublished(), 403);
        } else {
            $this->authorizeTeacher($course);
        }

        abort_if(! $assignment->file_path, 404, 'Bài tập này không có file đề bài.');

        return Storage::disk('private')->download(
            $assignment->file_path,
            $assignment->original_name ?: 'de-bai.pdf'
        );
    }

    public function viewAssignmentFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->authorizeTeacher($course);
        abort_if(! $assignment->file_path, 404, 'Bài tập này không có file đề bài.');

        $disk = Storage::disk('private');
        abort_unless($disk->exists($assignment->file_path), 404);
        $mimeType = $disk->mimeType($assignment->file_path) ?: 'application/octet-stream';
        $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png'];

        if (! in_array($mimeType, $inlineTypes, true)) {
            return $disk->download($assignment->file_path, $assignment->original_name ?: 'de-bai');
        }

        return $disk->response($assignment->file_path, $assignment->original_name ?: 'de-bai', [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($assignment->original_name ?: 'de-bai').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function viewStudentAssignmentFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id || ! $assignment->isPublished(), 404);
        $this->checkEnrolled($course);
        abort_if(! $assignment->file_path, 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($assignment->file_path), 404);
        $mimeType = $disk->mimeType($assignment->file_path) ?: 'application/octet-stream';
        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return $disk->download($assignment->file_path, $assignment->original_name ?: 'de-bai');
        }

        return $disk->response($assignment->file_path, $assignment->original_name ?: 'de-bai', [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($assignment->original_name ?: 'de-bai').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadStudentAnswerFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id || ! $assignment->isPublished(), 404);
        $this->checkEnrolled($course);
        abort_unless($assignment->show_answer_to_students && $assignment->answer_file_path, 403);
        abort_unless($assignment->submissions()->where('user_id', Auth::id())->exists(), 403, 'Bạn cần nộp bài trước khi xem đáp án.');

        $disk = Storage::disk('private');
        $mimeType = $disk->mimeType($assignment->answer_file_path) ?: 'application/octet-stream';
        if (in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return $disk->response($assignment->answer_file_path, $assignment->answer_original_name ?: 'dap-an', [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.addslashes($assignment->answer_original_name ?: 'dap-an').'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $disk->download($assignment->answer_file_path, $assignment->answer_original_name ?: 'dap-an');
    }

    public function downloadAnswerFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->authorizeTeacher($course);
        abort_if(! $assignment->answer_file_path, 404, 'Bài tập này không có file đáp án.');

        return Storage::disk('private')->download(
            $assignment->answer_file_path,
            $assignment->answer_original_name ?: 'dap-an.pdf'
        );
    }

    public function viewAnswerFile(Course $course, Assignment $assignment)
    {
        abort_if($assignment->course_id !== $course->id, 404);
        $this->authorizeTeacher($course);
        abort_if(! $assignment->answer_file_path, 404, 'Bài tập này không có file đáp án.');

        $disk = Storage::disk('private');
        abort_unless($disk->exists($assignment->answer_file_path), 404);
        $mimeType = $disk->mimeType($assignment->answer_file_path) ?: 'application/octet-stream';
        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return $disk->download($assignment->answer_file_path, $assignment->answer_original_name ?: 'dap-an');
        }

        return $disk->response($assignment->answer_file_path, $assignment->answer_original_name ?: 'dap-an', [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($assignment->answer_original_name ?: 'dap-an').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function authorizeTeacher(Course $course): void
    {
        $user = Auth::user();
        abort_if(
            ! $user->hasRole('admin') && $course->teacher_id !== $user->id,
            403
        );
    }

    private function checkEnrolled(Course $course): void
    {
        abort_unless(
            $course->enrollments()->where('user_id', Auth::id())->where('status', 'active')->exists(),
            403,
            'Bạn chưa ghi danh khóa học này.'
        );
    }
}
