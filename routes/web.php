<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceLocationController;
use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\CalendarSharingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExposureController;
use App\Http\Controllers\HrdController;
use App\Http\Controllers\InternController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectHandleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SkillSetController;
use App\Http\Controllers\WorkFromHomeRequestController;
use Illuminate\Support\Facades\Route;

Route::pattern('achievement', '[0-9]+');
Route::pattern('analytic', '[0-9]+');
Route::pattern('attendance', '[0-9]+');
Route::pattern('attendanceLocation', '[0-9]+');
Route::pattern('calendar_sharing', '[0-9]+');
Route::pattern('intern', '[0-9]+');
Route::pattern('hrd', '[0-9]+');
Route::pattern('leaderboard', '[0-9]+');
Route::pattern('mentor', '[0-9]+');
Route::pattern('notification', '[0-9]+');
Route::pattern('workFromHomeRequest', '[0-9]+');
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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index')->middleware('kmi.access:dashboard');
    Route::post('/logout', [AuthPageController::class, 'logout'])->name('logout');

    Route::resource('projects', ProjectController::class)->only(['index', 'show'])->middleware('kmi.access:projects');
    Route::resource('projects', ProjectController::class)->except(['index', 'show'])->middleware('kmi.access:crud-projects');

    Route::resource('calendar-sharing', CalendarSharingController::class)->only(['index', 'show'])->middleware('kmi.access:calendar-sharing');
    Route::resource('calendar-sharing', CalendarSharingController::class)->except(['index', 'show'])->middleware('kmi.access:crud-calendar-sharing');

    Route::get('exposure', [ExposureController::class, 'index'])->name('exposure.index')->middleware('kmi.access:exposure');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index')->middleware('kmi.access:attendance');
    Route::post('face-detection', [AttendanceController::class, 'detectFace'])->name('face.detection.store')->middleware('kmi.access:attendance');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in.store')->middleware('kmi.access:attendance');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out.store')->middleware('kmi.access:attendance');
    Route::put('attendance/settings', [AttendanceController::class, 'updateSettings'])->name('attendance.settings.update')->middleware('kmi.access:attendance-admin');
    Route::post('attendance/locations', [AttendanceLocationController::class, 'store'])->name('attendance-locations.store')->middleware('kmi.access:attendance-admin');
    Route::put('attendance/locations/{attendanceLocation}', [AttendanceLocationController::class, 'update'])->name('attendance-locations.update')->middleware('kmi.access:attendance-admin');
    Route::delete('attendance/locations/{attendanceLocation}', [AttendanceLocationController::class, 'destroy'])->name('attendance-locations.destroy')->middleware('kmi.access:attendance-admin');
    Route::get('attendance/export/excel', [AttendanceController::class, 'exportExcel'])->name('attendance.export.excel')->middleware('kmi.access:attendance-admin');
    Route::get('attendance/report/pdf', [AttendanceController::class, 'reportPdf'])->name('attendance.report.pdf')->middleware('kmi.access:attendance-admin');
    Route::get('attendance/salary-slip/pdf', [AttendanceController::class, 'salarySlipPdf'])->name('attendance.salary-slip.pdf')->middleware('kmi.access:attendance-admin');

    Route::get('work-from-home', [WorkFromHomeRequestController::class, 'index'])->name('work-from-home.index')->middleware('kmi.access:work-from-home');
    Route::post('work-from-home', [WorkFromHomeRequestController::class, 'store'])->name('work-from-home.store')->middleware('kmi.access:work-from-home');
    Route::patch('work-from-home/{workFromHomeRequest}/approve', [WorkFromHomeRequestController::class, 'approve'])->name('work-from-home.approve')->middleware('kmi.access:work-from-home-admin');
    Route::patch('work-from-home/{workFromHomeRequest}/reject', [WorkFromHomeRequestController::class, 'reject'])->name('work-from-home.reject')->middleware('kmi.access:work-from-home-admin');
    Route::patch('work-from-home/{workFromHomeRequest}/cancel', [WorkFromHomeRequestController::class, 'cancel'])->name('work-from-home.cancel')->middleware('kmi.access:work-from-home');
    Route::get('work-from-home/{workFromHomeRequest}/attachment', [WorkFromHomeRequestController::class, 'attachment'])->name('work-from-home.attachment')->middleware('kmi.access:work-from-home');

    Route::resource('leaderboard', LeaderboardController::class)->only(['index', 'show'])->middleware('kmi.access:leaderboard');

    Route::resource('interns', InternController::class)->only(['index', 'show'])->middleware('kmi.access:master-data');
    Route::resource('interns', InternController::class)->except(['index', 'show'])->middleware('kmi.access:master-data');
    Route::resource('mentors', MentorController::class)->only(['index', 'show'])->middleware('kmi.access:master-data');
    Route::resource('mentors', MentorController::class)->except(['index', 'show'])->middleware('kmi.access:master-data');
    Route::resource('hrds', HrdController::class)->only(['index', 'show'])->middleware('kmi.access:hrd-data');
    Route::resource('hrds', HrdController::class)->except(['index', 'show'])->middleware('kmi.access:hrd-data');
    Route::resource('skill-sets', SkillSetController::class)->only(['index', 'show'])->middleware('kmi.access:master-data');
    Route::resource('skill-sets', SkillSetController::class)->except(['index', 'show'])->middleware('kmi.access:master-data');

    Route::resource('project-handles', ProjectHandleController::class)->only(['index', 'show'])->middleware('kmi.access:project-handles');
    Route::put('project-handles/weights', [ProjectHandleController::class, 'updateWeights'])->name('project-handles.weights.update')->middleware('kmi.access:project-handles');
    Route::resource('project-handles', ProjectHandleController::class)->except(['index', 'show'])->middleware('kmi.access:project-handles');

    Route::resource('analytics', AnalyticsController::class)->only(['index', 'show'])->middleware('kmi.access:analytics');
    Route::get('analytics/{analytic}/certificate', [AnalyticsController::class, 'certificate'])->name('analytics.certificate')->middleware('kmi.access:analytics');
    Route::patch('analytics/{analytic}/certificate/publish', [AnalyticsController::class, 'publishCertificate'])->name('analytics.certificate.publish')->middleware('kmi.access:crud-analytics');
    Route::resource('analytics', AnalyticsController::class)->except(['index', 'show'])->middleware('kmi.access:crud-analytics');
    Route::resource('achievements', AchievementController::class)->only(['index', 'show'])->middleware('kmi.access:achievements');
    Route::resource('achievements', AchievementController::class)->except(['index', 'show'])->middleware('kmi.access:crud-achievements');
    Route::resource('reports', ReportController::class)->only(['index', 'show'])->middleware('kmi.access:reports');
    Route::resource('reports', ReportController::class)->except(['index', 'show'])->middleware('kmi.access:reports');
    Route::resource('settings', SettingController::class)->middleware('kmi.access:settings');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::post('/profile/face-enrollment', [ProfileController::class, 'storeFaceEnrollment'])->name('profile.face-enrollment.store');
    Route::delete('/profile/face-enrollment', [ProfileController::class, 'destroyFaceEnrollment'])->name('profile.face-enrollment.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/intern/{intern}', [ProfileController::class, 'showIntern'])->name('profile.intern.show');
    Route::get('/profile/mentor/{mentor}', [ProfileController::class, 'showMentor'])->name('profile.mentor.show');
});
