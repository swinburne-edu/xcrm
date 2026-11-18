<?php

use App\Domain\Major\Jobs\AggregateQuotaProgress;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Task 2.3: quota snapshots and warnings, every hour (needs the scheduler: php artisan schedule:work, or a cron entry).
Artisan::command('crm:aggregate-quotas', function () {
    AggregateQuotaProgress::dispatchSync();
    $this->info('Quota progress aggregated.');
})->purpose('Store the daily quota snapshot per major and notify managers of new warnings');

Schedule::command('crm:aggregate-quotas')->hourly()->withoutOverlapping();
