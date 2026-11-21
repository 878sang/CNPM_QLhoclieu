<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiTutorController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentProgressController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\VideoInteractionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : view('welcome'));

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/auth/google', [AuthController::class, 'redirectGoogle'])->name('google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('google.callback');
    Route::get('/quen-mat-khau', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/quen-mat-khau', [AuthController::class, 'sendReset'])->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [AuthController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/dang-xuat', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/tai-khoan', [AdminController::class, 'users'])->name('users.index');
        Route::get('/tai-khoan/tao-moi', [AdminController::class, 'createUser'])->name('users.create');
        Route::post('/tai-khoan', [AdminController::class, 'storeUser'])->name('users.store');
        Route::resource('mon-hoc', SubjectController::class)->parameters(['mon-hoc' => 'subject'])->except(['show'])->names('subjects');
        Route::get('/tai-lieu', [DocumentReviewController::class, 'index'])->name('documents.index');
        Route::post('/tai-lieu', [DocumentController::class, 'adminStore'])->name('documents.store');
        Route::post('/tai-lieu/{document}/duyet', [DocumentReviewController::class, 'approve'])->name('documents.approve');
        Route::post('/tai-lieu/{document}/tu-choi', [DocumentReviewController::class, 'reject'])->name('documents.reject');
        Route::post('/bai-tap/{assignment}/duyet', [DocumentReviewController::class, 'approveAssignment'])->name('assignments.approve');
        Route::post('/bai-tap/{assignment}/tu-choi', [DocumentReviewController::class, 'rejectAssignment'])->name('assignments.reject');
        Route::post('/bai-hoc/{lesson}/duyet', [LessonController::class, 'approve'])->name('lessons.approve');
        Route::post('/bai-hoc/{lesson}/tu-choi', [LessonController::class, 'reject'])->name('lessons.reject');
    });

    Route::get('/quan-ly-khoa-hoc', [CourseController::class, 'index'])->middleware('role:teacher')->name('teacher.dashboard');
    Route::get('/tai-lieu', [DocumentController::class, 'manage'])->middleware('role:teacher')->name('teacher.materials');
    Route::middleware('role:teacher')->prefix('giang-vien')->name('teacher.materials.')->group(function () {
        Route::get('/tai-lieu', [DocumentController::class, 'teacherIndex'])->name('index');
        Route::post('/tai-lieu', [DocumentController::class, 'teacherStore'])->name('store');
    });
    Route::get('/thong-ke', fn () => view('placeholder', ['title' => 'Thống kê']))->middleware('role:teacher')->name('teacher.statistics');

    Route::middleware('role:admin,teacher')->prefix('khoa-hoc')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::get('/tao-moi', [CourseController::class, 'create'])->name('create');
        Route::post('/', [CourseController::class, 'store'])->name('store');
        Route::get('/{course}/sua', [CourseController::class, 'edit'])->name('edit');
        Route::put('/{course}', [CourseController::class, 'update'])->name('update');
        Route::get('/{course}', [CourseController::class, 'show'])->name('show');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->name('destroy');

        // Enrollments
        Route::get('/{course}/hoc-vien', [EnrollmentController::class, 'index'])->name('students.index');
        Route::post('/{course}/hoc-vien', [EnrollmentController::class, 'store'])->name('students.store');
        Route::delete('/{course}/hoc-vien/{enrollment}', [EnrollmentController::class, 'destroy'])->scopeBindings()->name('students.destroy');

        // Chapters & Lessons (TS2-02, TS2-03)
        Route::post('/{course}/chuong', [ChapterController::class, 'store'])->name('chapters.store');
        Route::put('/{course}/chuong/{chapter}', [ChapterController::class, 'update'])->name('chapters.update');
        Route::delete('/{course}/chuong/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');

        Route::post('/{course}/chuong/{chapter}/bai-hoc', [LessonController::class, 'store'])->name('chapters.lessons.store');
        Route::put('/{course}/chuong/{chapter}/bai-hoc/{lesson}', [LessonController::class, 'update'])->name('chapters.lessons.update');
        Route::delete('/{course}/chuong/{chapter}/bai-hoc/{lesson}', [LessonController::class, 'destroy'])->name('chapters.lessons.destroy');

        // Documents (TS2-04, TS2-05, TS2-07)
        Route::get('/{course}/tai-lieu', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/{course}/tai-lieu', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{course}/tai-lieu/{document}/sua', [DocumentController::class, 'edit'])->scopeBindings()->name('documents.edit');
        Route::put('/{course}/tai-lieu/{document}', [DocumentController::class, 'update'])->scopeBindings()->name('documents.update');
        Route::delete('/{course}/tai-lieu/{document}', [DocumentController::class, 'destroy'])->scopeBindings()->name('documents.destroy');

        // Tương tác trên video (Interactive Video Questions)
        Route::get('/{course}/video/{document}/tuong-tac', [VideoInteractionController::class, 'manage'])->scopeBindings()->name('video-interactions.manage');
        Route::post('/{course}/video/{document}/tuong-tac', [VideoInteractionController::class, 'store'])->scopeBindings()->name('video-interactions.store');
        Route::put('/{course}/video/{document}/tuong-tac/{interaction}', [VideoInteractionController::class, 'update'])->name('video-interactions.update');
        Route::delete('/{course}/video/{document}/tuong-tac/{interaction}', [VideoInteractionController::class, 'destroy'])->name('video-interactions.destroy');

        // Assignments (TS3-07, TS3-08)
        Route::get('/{course}/bai-tap', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/{course}/bai-tap/tao-moi', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/{course}/bai-tap', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/{course}/bai-tap/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
        Route::get('/{course}/bai-tap/{assignment}/sua', [AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('/{course}/bai-tap/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/{course}/bai-tap/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
        // Submissions management (teacher)
        Route::get('/{course}/bai-tap/{assignment}/bai-nop', [SubmissionController::class, 'index'])->name('assignments.submissions.index');
        Route::get('/{course}/bai-tap/{assignment}/bai-nop/{submission}', [SubmissionController::class, 'teacherShow'])->name('assignments.submissions.show');
        Route::post('/{course}/bai-tap/{assignment}/bai-nop/{submission}/cham-diem', [SubmissionController::class, 'grade'])->name('assignments.submissions.grade');
        Route::get('/{course}/bai-tap/{assignment}/bai-nop/{submission}/tai-xuong', [SubmissionController::class, 'downloadFile'])->name('assignments.submissions.download');
        Route::get('/{course}/bai-tap/{assignment}/bai-nop/{submission}/xem-file', [SubmissionController::class, 'viewFile'])->name('assignments.submissions.preview_file');
        Route::get('/{course}/bai-tap/{assignment}/tai-de', [SubmissionController::class, 'downloadAssignmentFile'])->name('assignments.download_file');
        Route::get('/{course}/bai-tap/{assignment}/xem-de', [SubmissionController::class, 'viewAssignmentFile'])->name('assignments.preview_file');
        Route::get('/{course}/bai-tap/{assignment}/tai-dap-an', [SubmissionController::class, 'downloadAnswerFile'])->name('assignments.answer_file');
        Route::get('/{course}/bai-tap/{assignment}/xem-dap-an', [SubmissionController::class, 'viewAnswerFile'])->name('assignments.preview_answer_file');
    });

    // Material view and download (TS2-05, TS3-03)
    Route::get('/khoa-hoc/{course}/tai-lieu/{document}/xem', [DocumentController::class, 'show'])->scopeBindings()->name('courses.documents.show');
    Route::get('/khoa-hoc/{course}/tai-lieu/{document}/tai-xuong', [DocumentController::class, 'download'])->scopeBindings()->name('courses.documents.download');

    // Student learning & progress (TS3-02, TS3-04, TS3-07, TS3-08)
    Route::middleware('role:student')->prefix('hoc-vien')->name('student.')->group(function () {
        Route::get('/', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::get('/khoa-hoc', [StudentController::class, 'courses'])->name('courses');
        Route::get('/khoa-hoc/{course}', [StudentController::class, 'show'])->name('courses.show');
        Route::get('/khoa-hoc/{course}/hoc/{lesson?}', [StudentController::class, 'learn'])->name('courses.learn');
        Route::post('/khoa-hoc/{course}/bai-hoc/{lesson}/hoan-thanh', [StudentController::class, 'toggleLessonProgress'])->name('lessons.toggle');
        Route::post('/khoa-hoc/{course}/bai-hoc/{lesson}/vi-tri-video', [StudentController::class, 'updateVideoPosition'])->name('lessons.video_position');
        Route::delete('/khoa-hoc/{course}/roi', [StudentController::class, 'leave'])->name('courses.leave');
        Route::get('/tien-do', [StudentController::class, 'progress'])->name('progress');
        Route::post('/tai-lieu/{document}/tien-do', [DocumentProgressController::class, 'store'])->name('documents.progress');
        Route::get('/tim-hoc-lieu', [StudentController::class, 'search'])->name('search');
        Route::get('/da-luu', fn () => view('placeholder', ['title' => 'Đã lưu']))->name('saved');
        // Assignment – học viên (TS3-09)
        Route::get('/khoa-hoc/{course}/bai-tap', [StudentController::class, 'assignments'])->name('courses.assignments');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/lam-bai', [StudentController::class, 'doAssignment'])->name('assignments.do');
        Route::post('/khoa-hoc/{course}/bai-tap/{assignment}/nop-bai', [SubmissionController::class, 'store'])->name('assignments.submit');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/ket-qua', [SubmissionController::class, 'show'])->name('assignments.result');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/tai-de', [SubmissionController::class, 'downloadAssignmentFile'])->name('assignments.download_file_student');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/xem-de', [SubmissionController::class, 'viewStudentAssignmentFile'])->name('assignments.preview_file_student');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/tai-dap-an', [SubmissionController::class, 'downloadStudentAnswerFile'])->name('assignments.answer_file');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/tai-bai-nop', [SubmissionController::class, 'downloadStudentSubmissionFile'])->name('assignments.download_submission_file');
        Route::get('/khoa-hoc/{course}/bai-tap/{assignment}/xem-bai-nop', [SubmissionController::class, 'viewStudentSubmissionFile'])->name('assignments.preview_submission_file');
    });

    Route::get('/giang-vien/hoc-vien', [TeacherController::class, 'students'])->middleware('role:teacher')->name('teacher.students');

    // Quản lý tương tác trên video (tách riêng với Hỏi đáp)
    Route::middleware('role:admin,teacher')->get('/tuong-tac-video', [VideoInteractionController::class, 'index'])->name('video-interactions.index');

    // Quản lý Hỏi đáp & Thảo luận bài giảng
    Route::middleware('role:admin,teacher')->prefix('tuong-tac')->name('interactions.')->group(function () {
        Route::get('/', [InteractionController::class, 'index'])->name('index');
        Route::post('/{interaction}/tra-loi', [InteractionController::class, 'reply'])->name('reply');
        Route::patch('/{interaction}/trang-thai', [InteractionController::class, 'updateStatus'])->name('status');
        Route::patch('/{interaction}/ghim', [InteractionController::class, 'togglePin'])->name('pin');
    });
    Route::post('/khoa-hoc/{course}/bai-hoc/{lesson}/tuong-tac', [InteractionController::class, 'store'])->name('interactions.store');
    Route::post('/tuong-tac/{interaction}/thich', [InteractionController::class, 'toggleLike'])->name('interactions.like');
    Route::delete('/tuong-tac/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');
    Route::patch('/tuong-tac/{interaction}', [InteractionController::class, 'update'])->name('interactions.update');

    // Học viên trả lời câu hỏi tương tác trên video
    Route::post('/hoc-vien/tuong-tac-video/{interaction}/tra-loi', [VideoInteractionController::class, 'answer'])->name('student.video-interactions.answer');

    // Trợ lý AI hỏi đáp theo video & tài liệu bài học
    Route::post('/hoc-vien/khoa-hoc/{course}/bai-hoc/{lesson}/tro-ly-ai/chuan-bi', [AiTutorController::class, 'prepare'])->name('student.ai-tutor.prepare');
    Route::post('/hoc-vien/khoa-hoc/{course}/bai-hoc/{lesson}/tro-ly-ai/stream', [AiTutorController::class, 'stream'])->name('student.ai-tutor.stream');
    Route::post('/hoc-vien/khoa-hoc/{course}/bai-hoc/{lesson}/tro-ly-ai', [AiTutorController::class, 'ask'])->name('student.ai-tutor.ask');
    Route::delete('/hoc-vien/khoa-hoc/{course}/bai-hoc/{lesson}/tro-ly-ai', [AiTutorController::class, 'reset'])->name('student.ai-tutor.reset');
    Route::get('/hoc-vien/tro-ly-ai/anh/{message}', [AiTutorController::class, 'image'])->name('student.ai-tutor.image');

    // Quản lý thông báo (tất cả vai trò)
    Route::prefix('thong-bao')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/tao-moi', [NotificationController::class, 'create'])->middleware('role:admin,teacher')->name('create');
        Route::post('/', [NotificationController::class, 'store'])->middleware('role:admin,teacher')->name('store');
        Route::post('/doc-tat-ca', [NotificationController::class, 'markAllRead'])->name('mark_all_read');
        Route::get('/{notification}/doc', [NotificationController::class, 'read'])->name('read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    Route::get('/doi-mat-khau', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/doi-mat-khau', [AuthController::class, 'updatePassword'])->name('password.change');
});
