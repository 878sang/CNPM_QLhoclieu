<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
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
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::middleware('role:admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/tai-khoan', [AdminController::class, 'users'])->name('users.index');
        Route::get('/tai-khoan/tao-moi', [AdminController::class, 'createUser'])->name('users.create');
        Route::post('/tai-khoan', [AdminController::class, 'storeUser'])->name('users.store');
    });
    Route::get('/quan-ly-khoa-hoc', [CourseController::class, 'index'])->middleware('role:teacher')->name('teacher.dashboard');
    Route::middleware('role:admin,teacher')->prefix('khoa-hoc')->name('courses.')->group(function () {
        Route::get('/', [CourseController::class, 'index'])->name('index');
        Route::get('/tao-moi', [CourseController::class, 'create'])->name('create');
        Route::post('/', [CourseController::class, 'store'])->name('store');
        Route::get('/{course}/sua', [CourseController::class, 'edit'])->name('edit');
        Route::put('/{course}', [CourseController::class, 'update'])->name('update');
        Route::get('/{course}/hoc-vien', [EnrollmentController::class, 'index'])->name('students.index');
        Route::post('/{course}/hoc-vien', [EnrollmentController::class, 'store'])->name('students.store');
        Route::delete('/{course}/hoc-vien/{enrollment}', [EnrollmentController::class, 'destroy'])->scopeBindings()->name('students.destroy');
    });
    Route::get('/khu-vuc-hoc-vien', fn () => view('placeholder', ['title' => 'Khu vực học viên']))->middleware('role:student')->name('student.dashboard');
    Route::get('/doi-mat-khau', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/doi-mat-khau', [AuthController::class, 'updatePassword'])->name('password.change');
});
