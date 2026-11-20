<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $canSend = $user->hasRole(['admin', 'teacher']);
        $tab = $canSend && $request->query('tab') === 'sent' ? 'sent' : 'inbox';

        $inboxQuery = AppNotification::with(['sender:id,name', 'course:id,title'])
            ->where('user_id', $user->id)
            ->when($request->query('status') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($request->query('status') === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->input('q'));
                $q->where(fn ($sub) => $sub->where('title', 'like', "%{$term}%")->orWhere('message', 'like', "%{$term}%"));
            })
            ->latest();

        $sentNotifications = null;
        if ($canSend) {
            $sentQuery = AppNotification::with(['user:id,name,email', 'course:id,title'])
                ->when(! $user->hasRole('admin'), fn ($q) => $q->where('sender_id', $user->id))
                ->when($user->hasRole('admin'), fn ($q) => $q->whereNotNull('sender_id'))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
                ->when($request->filled('q'), function ($q) use ($request): void {
                    $term = trim((string) $request->input('q'));
                    $q->where(fn ($sub) => $sub->where('title', 'like', "%{$term}%")->orWhere('message', 'like', "%{$term}%"));
                })
                ->latest();

            $sentNotifications = $sentQuery->paginate(15, ['*'], 'sent_page')->withQueryString();
        }

        $unreadCount = AppNotification::where('user_id', $user->id)->unread()->count();
        $totalCount = AppNotification::where('user_id', $user->id)->count();

        return view('notifications.index', [
            'tab' => $tab,
            'canSend' => $canSend,
            'notifications' => $inboxQuery->paginate(15)->withQueryString(),
            'sentNotifications' => $sentNotifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function create(): View
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->hasRole(['admin', 'teacher']), 403);

        $isAdmin = $user->hasRole('admin');
        $courses = $isAdmin
            ? Course::withCount('enrollments')->orderBy('title')->get()
            : Course::where('teacher_id', $user->id)->withCount('enrollments')->orderBy('title')->get();

        if ($isAdmin) {
            $users = User::with('role:id,name,display_name')
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get();
        } else {
            $studentIds = Enrollment::whereIn('course_id', $courses->pluck('id'))
                ->where('status', 'active')
                ->pluck('user_id')
                ->unique();
            $users = User::with('role:id,name,display_name')
                ->whereIn('id', $studentIds)
                ->orderBy('name')
                ->get();
        }

        return view('notifications.create', [
            'isAdmin' => $isAdmin,
            'courses' => $courses,
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->hasRole(['admin', 'teacher']), 403);
        $isAdmin = $user->hasRole('admin');

        $allowedTargets = $isAdmin ? ['all', 'role', 'course', 'user'] : ['course', 'user'];

        $validated = $request->validate([
            'target_type' => ['required', Rule::in($allowedTargets)],
            'target_role' => ['nullable', Rule::in(['student', 'teacher', 'admin'])],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(AppNotification::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
            'action_url' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề thông báo.',
            'message.required' => 'Vui lòng nhập nội dung thông báo.',
        ]);

        $courseId = null;
        $actionUrl = filled($validated['action_url'] ?? null) ? trim((string) $validated['action_url']) : null;

        if ($validated['target_type'] === 'all') {
            $recipientIds = User::where('id', '!=', $user->id)->pluck('id');
        } elseif ($validated['target_type'] === 'role') {
            $roleName = $validated['target_role'] ?? 'student';
            $recipientIds = User::whereRelation('role', 'name', $roleName)
                ->where('id', '!=', $user->id)
                ->pluck('id');
        } elseif ($validated['target_type'] === 'course') {
            $course = Course::findOrFail($validated['course_id']);
            if (! $isAdmin) {
                abort_unless($course->teacher_id === $user->id, 403);
            }
            $courseId = $course->id;
            $recipientIds = $course->enrollments()->where('status', 'active')->pluck('user_id');
            $actionUrl ??= route('student.courses.show', $course);
        } else {
            $recipientId = (int) ($validated['recipient_id'] ?? 0);
            abort_unless($recipientId > 0, 422, 'Vui lòng chọn người nhận.');

            if (! $isAdmin) {
                $teacherCourseIds = Course::where('teacher_id', $user->id)->pluck('id');
                $isMyStudent = Enrollment::whereIn('course_id', $teacherCourseIds)
                    ->where('user_id', $recipientId)
                    ->exists();
                abort_unless($isMyStudent, 403, 'Bạn chỉ có thể gửi thông báo tới học viên trong khóa học của mình.');
            }
            $recipientIds = collect([$recipientId]);
        }

        $sentCount = AppNotification::notifyUsers(
            $recipientIds,
            trim($validated['title']),
            trim($validated['message']),
            $validated['type'],
            $user->id,
            $courseId,
            $actionUrl
        );

        if ($sentCount === 0) {
            return back()->withInput()->withErrors(['target_type' => 'Không tìm thấy người nhận phù hợp để gửi thông báo.']);
        }

        return redirect()
            ->route('notifications.index', ['tab' => 'sent'])
            ->with('status', "Đã gửi thông báo thành công tới {$sentCount} người nhận.");
    }

    public function read(AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        $notification->markAsRead();

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('status', 'Đã đánh dấu thông báo là đã đọc.');
    }

    public function markAllRead(): RedirectResponse
    {
        AppNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }

    public function destroy(AppNotification $notification): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $canDelete = $notification->user_id === $user->id
            || $notification->sender_id === $user->id
            || $user->hasRole('admin');

        abort_unless($canDelete, 403);

        $notification->delete();

        return back()->with('status', 'Đã xóa thông báo.');
    }
}
