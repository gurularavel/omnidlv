<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 | Platform admin (/admin) for users flagged is_super. No tenant middleware here: the admin
 | sees every company. Loaded with the "web" middleware group from bootstrap/app.php.
 */

// Back from "sign in as": the session belongs to the company user then, not to the admin.
Route::post('admin/leave', [UserController::class, 'leave'])->middleware('auth')->name('admin.leave');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
    Route::post('companies/{company}/resume', [CompanyController::class, 'resume'])->name('companies.resume');
    Route::post('companies/{company}/staff', [CompanyController::class, 'storeStaff'])->name('companies.staff');
    Route::post('employments/{employment}/toggle', [CompanyController::class, 'toggleEmployment'])->name('employments.toggle');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users/{user}/password', [UserController::class, 'password'])->middleware('throttle:20,1')->name('users.password');
    Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');

    Route::resource('plans', PlanController::class)->except('show');
    Route::resource('faq', FaqController::class)->except('show')->parameters(['faq' => 'faq']);

    Route::get('mail', [MailSettingsController::class, 'edit'])->name('mail.edit');
    Route::put('mail', [MailSettingsController::class, 'update'])->name('mail.update');
    Route::post('mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:10,1')->name('mail.test');
});
