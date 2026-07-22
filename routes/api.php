<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InternshipController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WorkFromHomeController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    Route::middleware('api.token')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('dashboard', [CatalogController::class, 'dashboard']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::post('profile/photo', [ProfileController::class, 'photo']);
        Route::post('profile/face-enrollment', [ProfileController::class, 'faceEnrollment']);
        Route::delete('profile/face-enrollment', [ProfileController::class, 'removeFaceEnrollment']);

        Route::get('projects', [CatalogController::class, 'projects']);
        Route::get('projects/{project}', [CatalogController::class, 'projectDetail']);
        Route::get('calendar-sharings', [CatalogController::class, 'calendarSharings']);
        Route::get('skill-sets', [CatalogController::class, 'skillSets']);
        Route::get('project-handles', [CatalogController::class, 'projectHandles']);
        Route::get('interns', [CatalogController::class, 'interns']);
        Route::get('interns/{intern}', [CatalogController::class, 'internDetail']);
        Route::get('mentors', [CatalogController::class, 'mentors']);
        Route::get('achievements', [CatalogController::class, 'achievements']);
        Route::get('evaluations', [CatalogController::class, 'evaluations']);
        Route::get('leaderboard', [CatalogController::class, 'leaderboard']);

        Route::get('me/internship', [InternshipController::class, 'summary']);
        Route::get('me/projects', [InternshipController::class, 'projects']);
        Route::patch('me/projects/{assignment}', [InternshipController::class, 'updateProject']);
        Route::get('me/evaluations', [InternshipController::class, 'evaluations']);
        Route::get('me/evaluations/{evaluation}/certificate', [InternshipController::class, 'certificate'])
            ->name('api.v1.me.evaluations.certificate');
        Route::get('me/achievements', [InternshipController::class, 'achievements']);

        Route::get('attendance', [AttendanceController::class, 'apiIndex']);
        Route::post('attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('attendance/locations', [CatalogController::class, 'attendanceLocations']);

        Route::get('work-from-home', [WorkFromHomeController::class, 'index']);
        Route::post('work-from-home', [WorkFromHomeController::class, 'store']);
        Route::post('work-from-home/{workFromHomeRequest}/approve', [WorkFromHomeController::class, 'approve']);
        Route::post('work-from-home/{workFromHomeRequest}/reject', [WorkFromHomeController::class, 'reject']);
        Route::post('work-from-home/{workFromHomeRequest}/cancel', [WorkFromHomeController::class, 'cancel']);
        Route::get('work-from-home/{workFromHomeRequest}/attachment', [WorkFromHomeController::class, 'attachment'])->name('api.v1.wfh.attachment');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    });
});
