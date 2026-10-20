<?php

use App\Http\Controllers\TrackingPageController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');

Route::get('t/{code}', [TrackingPageController::class, 'show'])
    ->middleware('throttle:tracking')
    ->name('tracking.show');
