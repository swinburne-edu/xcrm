<?php

namespace App\Domain\Workflow\Enums;

/** What a workflow runs on. The value is the morph alias of the model. */
enum WorkflowObject: string
{
    case AdmissionCase = 'admission_case';
    case Applicant = 'applicant';

    public function label(): string
    {
        return match ($this) {
            self::AdmissionCase => 'Hồ sơ tuyển sinh',
            self::Applicant => 'Thí sinh',
        };
    }
}
