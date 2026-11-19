<?php

namespace App\Domain\Platform\Enums;

/** The list screens that can keep saved views, with the page each one is applied to. */
enum SavedViewObject: string
{
    case Applicants = 'applicants';
    case AdmissionCases = 'admission_cases';
    case HighSchools = 'high_schools';

    public function label(): string
    {
        return match ($this) {
            self::Applicants => 'Thí sinh',
            self::AdmissionCases => 'Hồ sơ tuyển sinh',
            self::HighSchools => 'Trường THPT',
        };
    }

    /** Page per view type: cases have a Kanban board and a table. @return array<string, string> */
    public function paths(): array
    {
        return match ($this) {
            self::Applicants => ['table' => '/applicants'],
            self::AdmissionCases => ['kanban' => '/cases', 'table' => '/cases/table'],
            self::HighSchools => ['table' => '/high-schools'],
        };
    }

    /** Permission needed to open the screen (and so to keep views on it). */
    public function permission(): string
    {
        return match ($this) {
            self::Applicants => 'applicant.view',
            self::AdmissionCases => 'case.view',
            self::HighSchools => 'high_school.view',
        };
    }

    /** @return list<string> */
    public function viewTypes(): array
    {
        return array_keys($this->paths());
    }
}
