<?php

use App\Http\Controllers\SiteController;
use App\Http\Controllers\TrackingPageController;
use Illuminate\Support\Facades\Route;

// The marketing site is its own surface (built later); until then the root opens the panel.
Route::redirect('/', '/app')->name('home');

// Public pages linked from the footer.
Route::get('faq', [SiteController::class, 'faq'])->name('site.faq');
Route::get('terms', [SiteController::class, 'terms'])->name('site.terms');
Route::get('cookies', [SiteController::class, 'cookies'])->name('site.cookies');

// Public order tracking. /track is where a recipient types a code; /t/{code} is the link sent by SMS.
Route::get('track', [TrackingPageController::class, 'lookup'])
    ->middleware('throttle:tracking-lookup')
    ->name('tracking.lookup');

// Demo store that shows how a shop embeds tracking (static files in public/demo-shop).
Route::redirect('demo-shop', '/demo-shop/index.html');

Route::get('t/{code}', [TrackingPageController::class, 'show'])
    ->middleware('throttle:tracking')
    ->name('tracking.show');

Route::post('t/{code}/rate', [TrackingPageController::class, 'rate'])
    ->middleware('throttle:10,1')
    ->name('tracking.rate');
