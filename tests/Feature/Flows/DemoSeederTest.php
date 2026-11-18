<?php

use App\Domain\Activity\Models\Appointment;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\DuplicateFinder;
use App\Domain\Intake\Models\Form;
use App\Domain\Report\Services\FunnelReport;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertSuccessful();
    $this->head = User::query()->where('email', 'demo.head@example.com')->firstOrFail();
});

it('seeds a usable demo organization: users, leads in every stage, tasks, appointments and duplicates', function () {
    $this->actingAs($this->head);

    $cases = AdmissionCase::query()->with('stage')->get();

    expect($cases)->toHaveCount(62)->and($cases->pluck('stage.code')->unique()->count())->toBeGreaterThanOrEqual(8)
        ->and($cases->whereNull('owner_id')->count())->toBeGreaterThan(5)
        ->and(Applicant::query()->count())->toBe(66)
        ->and(Task::query()->count())->toBeGreaterThan(30)
        ->and(Appointment::query()->count())->toBeGreaterThan(3)
        ->and(Form::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'like', 'demo.%')->count())->toBe(6);

    expect(collect(app(DuplicateFinder::class)->groups())->pluck('reason')->all())->toBe(['same_school', 'same_school']);
});

it('feeds the dashboard and the reports', function () {
    $cycle = AdmissionCase::query()->first()->cycle;
    Auth::setUser($this->head);

    $overview = app(FunnelReport::class)->overview($cycle, $this->head);

    expect($overview['kpis']['total'])->toBe(62)->and($overview['kpis']['enrolled'])->toBe(2)->and($overview['kpis']['lost'])->toBe(4)
        ->and($overview['quotas'])->toHaveCount(5)->and($overview['top_sources'])->not->toBeEmpty();

    $this->actingAs($this->head)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('data.kpis.total', 62));
    $this->get('/reports?dimension=owner')->assertInertia(fn (Assert $p) => $p->has('rows'));
});

it('gives each counselor only their own leads and runs twice without duplicating', function () {
    $counselor = User::query()->where('email', 'demo.counselor1@example.com')->firstOrFail();

    $this->actingAs($counselor)->get('/cases/table?filter[owner]=mine')->assertInertia(fn (Assert $p) => $p->where('cases.meta.total', fn ($n) => $n > 5 && $n < 62));

    $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertSuccessful();

    expect(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(62);
});
