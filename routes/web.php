<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InternController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');

Route::resource('leaderboard', LeaderboardController::class);
Route::resource('interns', InternController::class);
Route::resource('projects', ProjectController::class);
Route::resource('mentors', MentorController::class);
Route::resource('analytics', AnalyticsController::class);
Route::resource('achievements', AchievementController::class);
Route::resource('reports', ReportController::class);
Route::resource('settings', SettingController::class);

Route::get('/login', [AuthPageController::class, 'login'])->name('login');
Route::get('/register', [AuthPageController::class, 'register'])->name('register');

Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
