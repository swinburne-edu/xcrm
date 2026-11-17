<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

it('creates the tables published by spatie packages', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'roles',
    'permissions',
    'model_has_roles',
    'model_has_permissions',
    'role_has_permissions',
    'activity_log',
    'media',
]);

it('registers the Excel facade', function () {
    expect(class_exists(Excel::class))->toBeTrue();
});

it('creates roles with spatie permission', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

    expect($role->exists)->toBeTrue()
        ->and(Role::findByName('admin', 'web')->is($role))->toBeTrue();
});

it('writes audit entries with spatie activitylog', function () {
    $user = User::factory()->create();

    activity()->causedBy($user)->log('exported applicants');

    $this->assertDatabaseHas('activity_log', [
        'description' => 'exported applicants',
        'causer_id' => $user->id,
    ]);
});

it('parses Vietnamese phone numbers with libphonenumber', function () {
    $util = PhoneNumberUtil::getInstance();
    $number = $util->parse('0912 345 678', 'VN');

    expect($util->isValidNumber($number))->toBeTrue()
        ->and(ltrim($util->format($number, PhoneNumberFormat::E164), '+'))->toBe('84912345678');
});
