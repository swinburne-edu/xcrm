<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Services\ScoreCalculator;
use App\Domain\Applicant\Enums\CertificateType;
use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->subject = fn (string $code) => Subject::query()->where('code', $code)->firstOrFail();
    $this->combination = fn (string $code) => SubjectCombination::query()->where('code', $code)->firstOrFail();

    /** A scored application: formula of the method, combination code, applicant attributes. */
    $this->application = function (array $formula, ?string $combination = 'A00', array $applicant = [], array $application = [], bool $direct = false): Application {
        $person = Applicant::factory()->for($this->org)->create($applicant);
        $case = app(CreateAdmissionCase::class)->handle($person, $this->cycle, [], $this->admin);
        $method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => $formula, 'is_direct' => $direct]);

        return Application::query()->create($application + [
            'organization_id' => $this->org->id,
            'case_id' => $case->id,
            'major_id' => Major::factory()->for($this->org)->create()->id,
            'cycle_method_id' => $method->id,
            'combination_id' => $combination === null ? null : ($this->combination)($combination)->id,
            'round_id' => AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id])->id,
            'preference_order' => 1,
        ]);
    };

    $this->score = function (Application $app, string $type, string $code, float $score, array $extra = []): void {
        ApplicantScore::query()->create([
            'applicant_id' => $app->case->applicant_id,
            'score_type' => $type,
            'subject_id' => ($this->subject)($code)->id,
            'score' => $score,
        ] + $extra);
    };

    $this->scored = function (array $formula, array $applicant, array $scores): Application {
        $app = ($this->application)($formula, 'A00', $applicant);
        foreach (['TOAN', 'LY', 'HOA'] as $i => $code) {
            ($this->score)($app, 'thpt_exam', $code, $scores[$i], ['year' => 2026]);
        }

        return $app;
    };

    $this->priority = fn () => [
        'priority_area_id' => PriorityArea::query()->where('code', 'KV1')->value('id'),
        'priority_group_id' => PriorityGroup::query()->where('code', 'UT1')->value('id'),
    ];
});

describe('per-subject sources', function () {
    it('sums the three subjects of the combination for the THPT exam', function () {
        $app = ($this->application)(['source' => 'thpt_exam', 'scale_to' => 30]);
        foreach (['TOAN' => 8, 'LY' => 7, 'HOA' => 9] as $code => $value) {
            ($this->score)($app, 'thpt_exam', $code, $value, ['year' => 2026]);
        }

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->raw)->toBe(24.0)->and($result->final)->toBe(24.0)->and($result->missing)->toBe([])
            ->and($result->breakdown['combination'])->toBe('A00')->and($result->breakdown['subjects'])->toHaveCount(3);
    });

    it('uses the latest THPT exam year of a subject', function () {
        $app = ($this->application)(['source' => 'thpt_exam']);
        ($this->score)($app, 'thpt_exam', 'TOAN', 5, ['year' => 2025]);
        ($this->score)($app, 'thpt_exam', 'TOAN', 9, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'LY', 9, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'HOA', 9, ['year' => 2026]);

        expect(app(ScoreCalculator::class)->calculate($app)->raw)->toBe(27.0);
    });

    it('averages the transcript over the terms listed in the formula', function () {
        $app = ($this->application)(['source' => 'transcript', 'terms' => ['10', '11', '12'], 'aggregate' => 'avg']);
        foreach (['10' => 8, '11' => 9, '12' => 10] as $term => $value) {
            ($this->score)($app, 'transcript', 'TOAN', $value, ['term' => $term]);
        }
        ($this->score)($app, 'transcript', 'TOAN', 1, ['term' => 'HK1-9']); // outside the listed terms
        foreach (['LY', 'HOA'] as $code) {
            ($this->score)($app, 'transcript', $code, 7, ['term' => '12']);
        }

        expect(app(ScoreCalculator::class)->calculate($app)->raw)->toBe(23.0); // 9 + 7 + 7
    });

    it('matches a whole grade ("12") to both of its semesters, and a single semester ("12-1") exactly', function () {
        $grades = ($this->application)(['source' => 'transcript', 'terms' => ['11', '12']]);
        $semester = ($this->application)(['source' => 'transcript', 'terms' => ['12-2']]);

        foreach ([$grades, $semester] as $app) {
            foreach (['TOAN', 'LY', 'HOA'] as $code) {
                ($this->score)($app, 'transcript', $code, 6, ['term' => '11-1']);
                ($this->score)($app, 'transcript', $code, 8, ['term' => '12-1']);
                ($this->score)($app, 'transcript', $code, 10, ['term' => '12-2']);
            }
        }

        // grades: (6 [11-1] + (8+10)/2 [12]) / 2 = 7.5 per subject → 22.5; semester: only 12-2 → 10 per subject → 30
        expect(app(ScoreCalculator::class)->calculate($grades)->raw)->toBe(22.5)->and(app(ScoreCalculator::class)->calculate($semester)->raw)->toBe(30.0);
    });

    it('can sum the terms instead of averaging them', function () {
        $app = ($this->application)(['source' => 'transcript', 'terms' => ['11', '12'], 'aggregate' => 'sum', 'subject_scale' => 20, 'scale_to' => 30]);
        foreach (['TOAN', 'LY', 'HOA'] as $code) {
            ($this->score)($app, 'transcript', $code, 8, ['term' => '11']);
            ($this->score)($app, 'transcript', $code, 8, ['term' => '12']);
        }

        // each subject 16 of 20 → (16+16+16)/3 × 30/20 = 24
        expect(app(ScoreCalculator::class)->calculate($app)->raw)->toBe(24.0);
    });

    it('rescales a score that is not out of 10', function () {
        $app = ($this->application)(['source' => 'thpt_exam']);
        ($this->score)($app, 'thpt_exam', 'TOAN', 4, ['max_score' => 5, 'year' => 2026]); // 8 / 10
        ($this->score)($app, 'thpt_exam', 'LY', 8, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'HOA', 8, ['year' => 2026]);

        expect(app(ScoreCalculator::class)->calculate($app)->raw)->toBe(24.0);
    });

    it('weights subjects: Toán × 2 makes the total an average of weights, scaled to the max', function () {
        $combination = SubjectCombination::factory()->create();
        $combination->subjects()->attach(($this->subject)('TOAN')->id, ['weight' => 2]);
        $combination->subjects()->attach(($this->subject)('LY')->id, ['weight' => 1]);
        $combination->subjects()->attach(($this->subject)('HOA')->id, ['weight' => 1]);
        $app = ($this->application)(['source' => 'thpt_exam'], null);
        $app->update(['combination_id' => $combination->id]);
        ($this->score)($app, 'thpt_exam', 'TOAN', 9, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'LY', 6, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'HOA', 6, ['year' => 2026]);

        expect(app(ScoreCalculator::class)->calculate($app->refresh())->raw)->toBe(22.5); // (18+6+6)/4 × 3
    });

    it('reports the subjects that have no score instead of guessing', function () {
        $app = ($this->application)(['source' => 'thpt_exam']);
        ($this->score)($app, 'thpt_exam', 'TOAN', 8, ['year' => 2026]);

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->raw)->toBeNull()->and($result->final)->toBeNull()->and($result->complete())->toBeFalse()
            ->and($result->missing)->toBe(['LY', 'HOA']);
    });

    it('needs a combination for a per-subject source', function () {
        $app = ($this->application)(['source' => 'thpt_exam'], null);

        expect(app(ScoreCalculator::class)->calculate($app)->missing)->toBe(['combination']);
    });
});

describe('certificate conversion', function () {
    $formula = fn () => ['source' => 'thpt_exam', 'certificate_conversion' => ['subject' => 'ANH', 'table' => ['IELTS' => ['5.0' => 8.0, '5.5' => 8.5, '6.5' => 9.5, '7.0' => 10]]]];

    it('replaces the foreign-language score by the certificate when that is higher', function () use ($formula) {
        $app = ($this->application)($formula(), 'D01');
        ($this->score)($app, 'thpt_exam', 'VAN', 7, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'TOAN', 7, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'ANH', 6, ['year' => 2026]);
        ApplicantCertificate::query()->create(['applicant_id' => $app->case->applicant_id, 'type' => CertificateType::Ielts, 'score' => '6.5']);

        $result = app(ScoreCalculator::class)->calculate($app->refresh());

        expect($result->raw)->toBe(23.5)->and(collect($result->breakdown['subjects'])->firstWhere('subject', 'ANH')['from_certificate'])->toBeTrue();
    });

    it('takes the highest threshold the certificate reaches, and never lowers a better exam score', function () use ($formula) {
        $app = ($this->application)($formula(), 'D01');
        ($this->score)($app, 'thpt_exam', 'VAN', 7, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'TOAN', 7, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'ANH', 9, ['year' => 2026]);
        ApplicantCertificate::query()->create(['applicant_id' => $app->case->applicant_id, 'type' => CertificateType::Ielts, 'score' => '5.5']); // → 8.5 < 9

        expect(app(ScoreCalculator::class)->calculate($app->refresh())->raw)->toBe(23.0);
    });

    it('can score a subject from a certificate alone, and ignores expired or too-low certificates', function () use ($formula) {
        $app = ($this->application)($formula(), 'D01');
        ($this->score)($app, 'thpt_exam', 'VAN', 7, ['year' => 2026]);
        ($this->score)($app, 'thpt_exam', 'TOAN', 7, ['year' => 2026]);
        ApplicantCertificate::query()->create(['applicant_id' => $app->case->applicant_id, 'type' => CertificateType::Ielts, 'score' => '4.5']);
        expect(app(ScoreCalculator::class)->calculate($app->refresh())->missing)->toBe(['ANH']);

        ApplicantCertificate::query()->create(['applicant_id' => $app->case->applicant_id, 'type' => CertificateType::Ielts, 'score' => '7.0', 'expires_at' => now()->subYear()]);
        expect(app(ScoreCalculator::class)->calculate($app->refresh())->missing)->toBe(['ANH']);

        ApplicantCertificate::query()->create(['applicant_id' => $app->case->applicant_id, 'type' => CertificateType::Ielts, 'score' => '7.0']);
        expect(app(ScoreCalculator::class)->calculate($app->refresh())->raw)->toBe(24.0);
    });
});

describe('priority and encouragement bonus', function () {
    it('adds the full KV + UT bonus below the taper threshold', function () {
        $app = ($this->scored)(['source' => 'thpt_exam', 'priority' => ['apply' => true, 'taper_from' => 22.5]], ($this->priority)(), [7, 6.5, 6.5]); // raw 20

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->raw)->toBe(20.0)->and($result->priorityBonus)->toBe(2.75)->and($result->final)->toBe(22.75)
            ->and($result->breakdown['priority'])->toMatchArray(['area' => 0.75, 'group' => 2.0, 'tapered' => false]);
    });

    it('tapers the bonus from 22.5: bonus × (30 − total) / 7.5', function () {
        $app = ($this->scored)(['source' => 'thpt_exam', 'priority' => ['apply' => true, 'taper_from' => 22.5]], ($this->priority)(), [8, 8, 8]); // raw 24

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->priorityBonus)->toBe(2.2)->and($result->final)->toBe(26.2)->and($result->breakdown['priority']['tapered'])->toBeTrue();
    });

    it('gives no bonus when the formula does not apply priority', function () {
        $app = ($this->scored)(['source' => 'thpt_exam'], ($this->priority)(), [7, 7, 7]);

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->priorityBonus)->toBe(0.0)->and($result->final)->toBe(21.0);
    });

    it('caps the encouragement bonus and then the total at the scale', function () {
        $app = ($this->scored)(['source' => 'thpt_exam', 'encourage_bonus_max' => 3], [], [9.5, 9.5, 9.5]); // raw 28.5
        $app->encourage_bonus = 5;

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->encourageBonus)->toBe(3.0)->and($result->final)->toBe(30.0);
    });

    it('does not cap the total when cap_total is false', function () {
        $app = ($this->scored)(['source' => 'thpt_exam', 'cap_total' => false, 'encourage_bonus_max' => 3], [], [9.5, 9.5, 9.5]);
        $app->encourage_bonus = 3;

        expect(app(ScoreCalculator::class)->calculate($app)->final)->toBe(31.5);
    });

    it('reports whether the raw score reaches the minimum', function () {
        $low = ($this->scored)(['source' => 'thpt_exam', 'min_total' => 18], [], [6, 6, 5]); // 17
        $ok = ($this->scored)(['source' => 'thpt_exam', 'min_total' => 18], [], [6, 6, 6]); // 18

        expect(app(ScoreCalculator::class)->calculate($low)->breakdown['meets_minimum'])->toBeFalse()
            ->and(app(ScoreCalculator::class)->calculate($ok)->breakdown['meets_minimum'])->toBeTrue();
    });
});

describe('exam sources and edge cases', function () {
    it('scales one exam total to the max score, taking the best exam', function () {
        $app = ($this->application)(['source' => 'competency_exam', 'scale_to' => 30], null);
        $applicant = $app->case->applicant_id;
        ApplicantScore::query()->create(['applicant_id' => $applicant, 'score_type' => ScoreType::CompetencyExam, 'exam_name' => 'HSA', 'score' => 90, 'max_score' => 150]);
        ApplicantScore::query()->create(['applicant_id' => $applicant, 'score_type' => ScoreType::CompetencyExam, 'exam_name' => 'V-ACT', 'score' => 105, 'max_score' => 150]);

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->raw)->toBe(21.0)->and($result->breakdown['exam']['name'])->toBe('V-ACT');
    });

    it('only looks at the named exam when the formula names one', function () {
        $app = ($this->application)(['source' => 'competency_exam', 'exam_name' => 'HSA'], null);
        ApplicantScore::query()->create(['applicant_id' => $app->case->applicant_id, 'score_type' => ScoreType::CompetencyExam, 'exam_name' => 'V-ACT', 'score' => 140, 'max_score' => 150]);

        expect(app(ScoreCalculator::class)->calculate($app)->missing)->toBe(['score']);

        ApplicantScore::query()->create(['applicant_id' => $app->case->applicant_id, 'score_type' => ScoreType::CompetencyExam, 'exam_name' => 'hsa', 'score' => 75, 'max_score' => 150]);

        expect(app(ScoreCalculator::class)->calculate($app->refresh())->raw)->toBe(15.0);
    });

    it('does not score direct admission', function () {
        $app = ($this->application)(['source' => 'thpt_exam'], null, [], [], direct: true);

        $result = app(ScoreCalculator::class)->calculate($app);

        expect($result->raw)->toBeNull()->and($result->breakdown['source'])->toBe('direct');
    });

    it('reports a missing formula or an unknown source', function () {
        expect(app(ScoreCalculator::class)->calculate(($this->application)([], 'A00'))->missing)->toBe(['formula'])
            ->and(app(ScoreCalculator::class)->calculate(($this->application)(['source' => 'magic'], 'A00'))->missing)->toBe(['source']);
    });

    it('turns into the columns of the applications table', function () {
        $app = ($this->application)(['source' => 'thpt_exam']);
        foreach (['TOAN', 'LY', 'HOA'] as $code) {
            ($this->score)($app, 'thpt_exam', $code, 8, ['year' => 2026]);
        }

        $attributes = app(ScoreCalculator::class)->calculate($app)->toAttributes();

        expect($attributes)->toMatchArray(['raw_score' => 24.0, 'final_score' => 24.0, 'priority_bonus' => 0.0])
            ->and($attributes['score_breakdown'])->toHaveKeys(['source', 'subjects', 'missing']);
    });
});
