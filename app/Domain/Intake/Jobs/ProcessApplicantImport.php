<?php

namespace App\Domain\Intake\Jobs;

use App\Domain\Intake\Actions\ApplicantImport;
use App\Domain\Intake\Models\ImportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs one applicant import in the background (queue worker: `php artisan queue:work`).
 */
class ProcessApplicantImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $importJobId) {}

    public function handle(ApplicantImport $import): void
    {
        $job = ImportJob::query()->withoutGlobalScopes()->find($this->importJobId);

        if ($job !== null && $job->status === 'pending') {
            $import->process($job);
        }
    }
}
