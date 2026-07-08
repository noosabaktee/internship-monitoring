<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\CalendarSharingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExposureController;
use App\Http\Controllers\InternController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectHandleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SkillSetController;
use Illuminate\Support\Facades\Route;

Route::pattern('achievement', '[0-9]+');
Route::pattern('analytic', '[0-9]+');
Route::pattern('attendance', '[0-9]+');
Route::pattern('calendar_sharing', '[0-9]+');
Route::pattern('intern', '[0-9]+');
Route::pattern('leaderboard', '[0-9]+');
Route::pattern('mentor', '[0-9]+');
Route::pattern('project', '[0-9]+');
Route::pattern('project_handle', '[0-9]+');
Route::pattern('report', '[0-9]+');
Route::pattern('setting', '[0-9]+');
Route::pattern('skill_set', '[0-9]+');

Route::middleware('kmi.guest')->group(function () {
    Route::get('/login', [AuthPageController::class, 'login'])->name('login');
    Route::post('/login', [AuthPageController::class, 'authenticate'])->name('login.authenticate');
    Route::get('/register', [AuthPageController::class, 'register'])->name('register');
    Route::post('/register', [AuthPageController::class, 'store'])->name('register.store');
});

Route::middleware('kmi.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::post('/logout', [AuthPageController::class, 'logout'])->name('logout');

    Route::resource('projects', ProjectController::class);
    Route::resource('calendar-sharing', CalendarSharingController::class);
    Route::get('exposure', [ExposureController::class, 'index'])->name('exposure.index');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance/face-enrollment', [AttendanceController::class, 'storeEnrollment'])->name('attendance.face-enrollment.store');
    Route::delete('attendance/face-enrollment', [AttendanceController::class, 'destroyEnrollment'])->name('attendance.face-enrollment.destroy');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in.store');

    Route::resource('leaderboard', LeaderboardController::class)->only(['index', 'show']);
    Route::resource('interns', InternController::class)->only(['index', 'show']);
    Route::resource('mentors', MentorController::class)->only(['index', 'show']);
    Route::resource('skill-sets', SkillSetController::class)->only(['index', 'show']);
    Route::resource('project-handles', ProjectHandleController::class)->only(['index', 'show']);
    Route::resource('analytics', AnalyticsController::class)->only(['index', 'show']);
    Route::resource('achievements', AchievementController::class)->only(['index', 'show']);
    Route::resource('reports', ReportController::class)->only(['index', 'show']);
    Route::resource('settings', SettingController::class)->only(['index', 'show']);

    Route::middleware('kmi.mentor')->group(function () {
        Route::resource('leaderboard', LeaderboardController::class)->except(['index', 'show']);
        Route::resource('interns', InternController::class)->except(['index', 'show']);
        Route::resource('mentors', MentorController::class)->except(['index', 'show']);
        Route::resource('skill-sets', SkillSetController::class)->except(['index', 'show']);
        Route::put('project-handles/weights', [ProjectHandleController::class, 'updateWeights'])->name('project-handles.weights.update');
        Route::resource('project-handles', ProjectHandleController::class)->except(['index', 'show']);
        Route::resource('analytics', AnalyticsController::class)->except(['index', 'show']);
        Route::resource('achievements', AchievementController::class)->except(['index', 'show']);
        Route::resource('reports', ReportController::class)->except(['index', 'show']);
        Route::resource('settings', SettingController::class)->except(['index', 'show']);
        Route::put('attendance/settings', [AttendanceController::class, 'updateSettings'])->name('attendance.settings.update');
    });

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/intern/{intern}', [ProfileController::class, 'showIntern'])->name('profile.intern.show');
    Route::get('/profile/mentor/{mentor}', [ProfileController::class, 'showMentor'])->name('profile.mentor.show');
});
