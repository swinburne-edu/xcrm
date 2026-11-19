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

// Task 2.9 (F5): cases for applicants graduating in the year of an active cycle, once a day (also dispatched when a cycle is activated).
Artisan::command('crm:create-graduating-cases', function () {
    \App\Domain\Cycle\Jobs\CreateCasesForGraduatingApplicantsJob::dispatchSync();
    $this->info('Graduating applicants processed.');
})->purpose('Open cases in every active cycle for applicants whose graduation year matches the cycle year');

Schedule::command('crm:create-graduating-cases')->dailyAt('02:00')->withoutOverlapping();

// Task 3.2: workflows with the trigger "schedule", once a day.
Artisan::command('crm:run-workflows', function () {
    \App\Domain\Workflow\Jobs\RunScheduledWorkflows::dispatchSync();
    $this->info('Scheduled workflows processed.');
})->purpose('Run the scheduled workflows on the records that have not been through them yet');

Schedule::command('crm:run-workflows')->dailyAt('07:00')->withoutOverlapping();
