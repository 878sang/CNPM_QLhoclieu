<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'));
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
    });
    Route::get('/quan-ly-khoa-hoc', [CourseController::class, 'index'])->middleware('role:teacher')->name('teacher.dashboard');
    Route::get('/tai-lieu', fn () => view('placeholder', ['title' => 'Tài liệu']))->middleware('role:teacher')->name('teacher.materials');
    Route::get('/thong-ke', fn () => view('placeholder', ['title' => 'Thống kê']))->middleware('role:teacher')->name('teacher.statistics');
    Route::middleware('role:admin,teacher')->prefix('khoa-hoc')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::get('/tao-moi', [CourseController::class, 'create'])->name('create');
        Route::post('/', [CourseController::class, 'store'])->name('store');
        Route::get('/{course}/sua', [CourseController::class, 'edit'])->name('edit');
        Route::put('/{course}', [CourseController::class, 'update'])->name('update');
        Route::get('/{course}', [CourseController::class, 'show'])->name('show');
        Route::delete('/{course}', [CourseController::class, 'destroy'])->name('destroy');
        Route::get('/{course}/hoc-vien', [EnrollmentController::class, 'index'])->name('students.index');
        Route::post('/{course}/hoc-vien', [EnrollmentController::class, 'store'])->name('students.store');
        Route::delete('/{course}/hoc-vien/{enrollment}', [EnrollmentController::class, 'destroy'])->scopeBindings()->name('students.destroy');
        Route::get('/{course}/tai-lieu', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/{course}/tai-lieu', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{course}/tai-lieu/{document}/sua', [DocumentController::class, 'edit'])->scopeBindings()->name('documents.edit');
        Route::put('/{course}/tai-lieu/{document}', [DocumentController::class, 'update'])->scopeBindings()->name('documents.update');
        Route::delete('/{course}/tai-lieu/{document}', [DocumentController::class, 'destroy'])->scopeBindings()->name('documents.destroy');
    });
    Route::get('/khoa-hoc/{course}/tai-lieu/{document}/xem', [DocumentController::class, 'show'])->scopeBindings()->name('courses.documents.show');
    Route::get('/khoa-hoc/{course}/tai-lieu/{document}/tai-xuong', [DocumentController::class, 'download'])->scopeBindings()->name('courses.documents.download');
    Route::middleware('role:student')->prefix('hoc-vien')->name('student.')->group(function () {
        Route::get('/', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::get('/khoa-hoc', [StudentController::class, 'courses'])->name('courses');
        Route::get('/khoa-hoc/{course}', [StudentController::class, 'show'])->name('courses.show');
        Route::delete('/khoa-hoc/{course}/roi', [StudentController::class, 'leave'])->name('courses.leave');
        Route::get('/tien-do', [StudentController::class, 'progress'])->name('progress');
        Route::get('/tim-hoc-lieu', [StudentController::class, 'search'])->name('search');
        Route::get('/da-luu', fn () => view('placeholder', ['title' => 'Đã lưu']))->name('saved');
    });
    Route::get('/giang-vien/hoc-vien', [TeacherController::class, 'students'])->middleware('role:teacher')->name('teacher.students');
    Route::get('/doi-mat-khau', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/doi-mat-khau', [AuthController::class, 'updatePassword'])->name('password.change');
});
