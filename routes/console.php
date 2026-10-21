<?php

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Webhook delivery log keeps 30 days (WebhookDelivery::prunable).
Schedule::command('model:prune', ['--model' => [WebhookDelivery::class]])->daily();
