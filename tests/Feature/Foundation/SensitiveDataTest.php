<?php

use App\Domain\Shared\Services\SensitiveData;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->mask = new SensitiveData;
});

it('masks phone numbers keeping the first and last two characters', function (string $input, string $expected) {
    expect($this->mask->maskPhone($input))->toBe($expected);
})->with([
    'national' => ['0912345678', '09******78'],
    'with spaces' => ['0912 345 678', '09******78'],
    'international' => ['+84912345678', '+8********78'],
    'short' => ['1234', '****'],
]);

it('masks citizen ids keeping the last three digits', function () {
    expect($this->mask->maskCitizenId('001203012345'))->toBe('*********345')
        ->and($this->mask->maskCitizenId('12'))->toBe('**');
});

it('shows sensitive data only with applicant.view_sensitive', function () {
    $this->seed(RoleSeeder::class);

    $counselor = User::factory()->create()->assignRole('counselor');   // has view_sensitive
    $ambassador = User::factory()->create()->assignRole('ambassador'); // does not

    expect($this->mask->phone('0912345678', $counselor))->toBe('0912345678')
        ->and($this->mask->phone('0912345678', $ambassador))->toBe('09******78')
        ->and($this->mask->phone('0912345678', null))->toBe('09******78')
        ->and($this->mask->phone(null, $ambassador))->toBeNull()
        ->and($this->mask->citizenId('001203012345', $ambassador))->toBe('*********345')
        ->and($this->mask->citizenId('', $ambassador))->toBe('');
});

it('writes an audit log entry when a full citizen id is viewed', function () {
    $this->seed(RoleSeeder::class);

    $clerk = User::factory()->create()->assignRole('admission_clerk');
    $subject = User::factory()->create(); // any mapped model stands in for an applicant

    $this->mask->citizenId('001203012345', $clerk, $subject);

    $entry = Activity::query()->latest('id')->first();

    expect($entry->description)->toBe('Xem số CCCD')
        ->and($entry->causer_id)->toBe($clerk->id)
        ->and($entry->subject_id)->toBe($subject->id);

    // A masked view is not a disclosure: nothing is logged.
    $count = Activity::count();
    $this->mask->citizenId('001203012345', User::factory()->create(), $subject);

    expect(Activity::count())->toBe($count);
});
