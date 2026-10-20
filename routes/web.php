<?php

use App\Http\Controllers\TrackingPageController;
use Illuminate\Support\Facades\Route;

// The marketing site is its own surface (built later); until then the root opens the panel.
Route::redirect('/', '/app')->name('home');

Route::get('t/{code}', [TrackingPageController::class, 'show'])
    ->middleware('throttle:tracking')
    ->name('tracking.show');

Route::post('t/{code}/rate', [TrackingPageController::class, 'rate'])
    ->middleware('throttle:10,1')
    ->name('tracking.rate');
