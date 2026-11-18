<?php

namespace Database\Factories;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicantDocument>
 */
class ApplicantDocumentFactory extends Factory
{
    protected $model = ApplicantDocument::class;

    public function definition(): array
    {
        return ['applicant_id' => Applicant::factory(), 'doc_type' => 'transcript', 'status' => 'pending'];
    }
}
