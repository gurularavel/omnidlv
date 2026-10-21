<?php

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:assign-pending')->everyMinute()->withoutOverlapping();
Schedule::command('slots:auto-close')->everyMinute()->withoutOverlapping();

// Webhook delivery log keeps 30 days (WebhookDelivery::prunable).
Schedule::command('model:prune', ['--model' => [WebhookDelivery::class]])->daily();
