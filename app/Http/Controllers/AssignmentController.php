<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    // ─── Giáo viên: Danh sách bài tập của khóa học ─────────────────────

    public function index(Course $course)
    {
        $this->authorizeTeacher($course);

        $assignments = $course->assignments()
            ->withCount('submissions')
            ->with('lesson')
            ->latest()
            ->get();

        $chapters = $course->chapters()->with(['lessons' => fn ($query) => $query->orderBy('position')])->orderBy('position')->get();

        return view('assignments.index', compact('course', 'assignments', 'chapters'));
    }

    // ─── Giáo viên: Form tạo bài tập ───────────────────────────────────

    public function create(Course $course)
    {
        $this->authorizeTeacher($course);
        $chapters = $course->chapters()->with('lessons')->orderBy('position')->get();
        $lessons = $chapters->pluck('lessons')->flatten();
        $selectedLessonId = request()->integer('lesson_id') ?: null;

        return view('assignments.form', compact('course', 'chapters', 'lessons', 'selectedLessonId'));
    }

    // ─── Giáo viên: Lưu bài tập mới ────────────────────────────────────

    public function store(Request $request, Course $course)
    {
        $this->authorizeTeacher($course);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:text,multiple_choice,mixed',
            'question_count' => 'required|integer|min:1|max:200',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'deadline' => 'nullable|date',
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->where(fn ($query) => $query->whereIn('chapter_id', $course->chapters()->select('id')))],
            'status' => 'required|in:draft,published',
            'show_answer_to_students' => 'sometimes|boolean',
            'question_types' => 'nullable|array',
            'question_types.*' => 'nullable|in:text,multiple_choice',
            'answer_key' => 'nullable|array',
            'answer_key.*' => 'nullable|string|max:50',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'answer_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
        ]);

        $questionTypes = $data['question_types'] ?? null;
        $type = $data['type'] ?? 'text';
        if (! empty($questionTypes)) {
            $hasMC = in_array('multiple_choice', $questionTypes, true);
            $hasText = in_array('text', $questionTypes, true);
            if ($hasMC && $hasText) {
                $type = 'mixed';
            } elseif ($hasMC) {
                $type = 'multiple_choice';
            } else {
                $type = 'text';
            }
        }

        // Lọc answer_key: chỉ giữ câu có đáp án
        $answerKey = null;
        if (! empty($data['answer_key'])) {
            $filtered = array_filter($data['answer_key'], fn ($v) => $v !== null && $v !== '');
            if (! empty($filtered)) {
                $answerKey = $filtered;
            }
        }

        // Upload file đề bài
        $filePath = null;
        $originalName = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("assignments/{$course->id}", 'private');
            $originalName = $request->file('file')->getClientOriginalName();
        }

        // Upload file đáp án
        $answerFilePath = null;
        $answerOriginalName = null;
        if ($request->hasFile('answer_file')) {
            $answerFilePath = $request->file('answer_file')->store("assignments/{$course->id}/answers", 'private');
            $answerOriginalName = $request->file('answer_file')->getClientOriginalName();
        }

        $course->assignments()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'question_types' => $questionTypes,
            'question_count' => $data['question_count'],
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'status' => $data['status'],
            'answer_key' => $answerKey,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'answer_file_path' => $answerFilePath,
            'answer_original_name' => $answerOriginalName,
            'show_answer_to_students' => $request->boolean('show_answer_to_students'),
        ]);

        return redirect()->route('courses.assignments.index', $course)
            ->with('status', 'Đã tạo bài tập "'.$data['title'].'" thành công!');
    }

    // ─── Giáo viên: Chi tiết bài tập + danh sách bài nộp ───────────────

    public function show(Course $course, Assignment $assignment)
    {
        $this->authorizeTeacher($course);
        $this->checkAssignmentBelongsToCourse($course, $assignment);

        $submissions = $assignment->submissions()
            ->with('student')
            ->latest('submitted_at')
            ->get();

        return view('assignments.show', compact('course', 'assignment', 'submissions'));
    }

    // ─── Giáo viên: Form sửa bài tập ───────────────────────────────────

    public function edit(Course $course, Assignment $assignment)
    {
        $this->authorizeTeacher($course);
        $this->checkAssignmentBelongsToCourse($course, $assignment);

        $chapters = $course->chapters()->with('lessons')->orderBy('position')->get();
        $lessons = $chapters->pluck('lessons')->flatten();

        return view('assignments.form', compact('course', 'assignment', 'chapters', 'lessons'));
    }

    // ─── Giáo viên: Cập nhật bài tập ───────────────────────────────────

    public function update(Request $request, Course $course, Assignment $assignment)
    {
        $this->authorizeTeacher($course);
        $this->checkAssignmentBelongsToCourse($course, $assignment);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:text,multiple_choice,mixed',
            'question_count' => 'required|integer|min:1|max:200',
            'time_limit_minutes' => 'nullable|integer|min:1|max:600',
            'deadline' => 'nullable|date',
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->where(fn ($query) => $query->whereIn('chapter_id', $course->chapters()->select('id')))],
            'status' => 'required|in:draft,published',
            'show_answer_to_students' => 'sometimes|boolean',
            'question_types' => 'nullable|array',
            'question_types.*' => 'nullable|in:text,multiple_choice',
            'answer_key' => 'nullable|array',
            'answer_key.*' => 'nullable|string|max:50',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'answer_file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
        ]);

        $questionTypes = array_key_exists('question_types', $data) ? $data['question_types'] : $assignment->question_types;
        $type = $data['type'] ?? $assignment->type;
        if (! empty($questionTypes)) {
            $hasMC = in_array('multiple_choice', $questionTypes, true);
            $hasText = in_array('text', $questionTypes, true);
            if ($hasMC && $hasText) {
                $type = 'mixed';
            } elseif ($hasMC) {
                $type = 'multiple_choice';
            } else {
                $type = 'text';
            }
        }

        $answerKey = $assignment->answer_key;
        if (array_key_exists('answer_key', $data)) {
            $filtered = array_filter($data['answer_key'] ?? [], fn ($v) => $v !== null && $v !== '');
            $answerKey = ! empty($filtered) ? $filtered : null;
        }

        $filePath = $assignment->file_path;
        $originalName = $assignment->original_name;
        if ($request->hasFile('file')) {
            if ($assignment->file_path) {
                Storage::disk('private')->delete($assignment->file_path);
            }
            $filePath = $request->file('file')->store("assignments/{$course->id}", 'private');
            $originalName = $request->file('file')->getClientOriginalName();
        }

        $answerFilePath = $assignment->answer_file_path;
        $answerOriginalName = $assignment->answer_original_name;
        if ($request->hasFile('answer_file')) {
            if ($assignment->answer_file_path) {
                Storage::disk('private')->delete($assignment->answer_file_path);
            }
            $answerFilePath = $request->file('answer_file')->store("assignments/{$course->id}/answers", 'private');
            $answerOriginalName = $request->file('answer_file')->getClientOriginalName();
        }

        $assignment->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'question_types' => $questionTypes,
            'question_count' => $data['question_count'],
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'status' => $data['status'],
            'answer_key' => $answerKey,
            'file_path' => $filePath,
            'original_name' => $originalName,
            'answer_file_path' => $answerFilePath,
            'answer_original_name' => $answerOriginalName,
            'show_answer_to_students' => $request->boolean('show_answer_to_students'),
        ]);

        return redirect()->route('courses.assignments.show', [$course, $assignment])
            ->with('status', 'Đã cập nhật bài tập thành công!');
    }

    // ─── Giáo viên: Xóa bài tập ────────────────────────────────────────

    public function destroy(Course $course, Assignment $assignment)
    {
        $this->authorizeTeacher($course);
        $this->checkAssignmentBelongsToCourse($course, $assignment);

        if ($assignment->file_path) {
            Storage::disk('private')->delete($assignment->file_path);
        }
        if ($assignment->answer_file_path) {
            Storage::disk('private')->delete($assignment->answer_file_path);
        }

        $assignment->delete();

        return redirect()->route('courses.assignments.index', $course)
            ->with('status', 'Đã xóa bài tập "'.$assignment->title.'".');
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function authorizeTeacher(Course $course): void
    {
        $user = Auth::user();
        abort_if(
            ! $user->hasRole('admin') && $course->teacher_id !== $user->id,
            403,
            'Bạn không có quyền quản lý khóa học này.'
        );
    }

    private function checkAssignmentBelongsToCourse(Course $course, Assignment $assignment): void
    {
        abort_if($assignment->course_id !== $course->id, 404);
    }
}
