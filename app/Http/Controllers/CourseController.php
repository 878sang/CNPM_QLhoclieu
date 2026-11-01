<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseRequest;
use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::with(['subject', 'teacher'])
            ->withCount('enrollments')
            ->unless($request->user()->hasRole('admin'), fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->latest()
            ->paginate(10);

        return view('courses.index', ['courses' => $courses]);
    }

    public function create(): View
    {
        $this->authorize('create', Course::class);

        return view('courses.form', $this->formData(new Course(['status' => 'draft'])));
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('cover_image');
        $data['teacher_id'] ??= $request->user()->id;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('courses', 'public');
        }

        Course::create($data);

        return redirect()->route('courses.index')->with('status', 'Khóa học đã được tạo.');
    }

    public function edit(Course $course): View
    {
        $this->authorize('update', $course);

        return view('courses.form', $this->formData($course));
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $data = $request->safe()->except('cover_image');
        $previousCover = $course->cover_image;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('courses', 'public');
        }

        $course->update($data);

        if (isset($data['cover_image']) && $previousCover) {
            Storage::disk('public')->delete($previousCover);
        }

        return redirect()->route('courses.index')->with('status', 'Khóa học đã được cập nhật.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Course $course): array
    {
        return [
            'course' => $course,
            'subjects' => Subject::orderBy('name')->get(),
            'teachers' => auth()->user()->hasRole('admin')
                ? User::whereRelation('role', 'name', 'teacher')->orderBy('name')->get()
                : collect(),
        ];
    }
}
