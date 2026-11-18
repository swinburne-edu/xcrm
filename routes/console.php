<?php

use App\Domain\Admission\Jobs\CheckCaseSla;
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

// Task 2.4: SLA alerts and automatic recall of idle cases, every 15 minutes (per the organization's settings).
Artisan::command('crm:check-sla', function () {
    CheckCaseSla::dispatchSync();
    $this->info('SLA checked.');
})->purpose('Alert team leaders about cases past their SLA and recall idle cases to the shared pool');

Schedule::command('crm:check-sla')->everyFifteenMinutes()->withoutOverlapping();
