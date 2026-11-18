<?php

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
});

function userPayload(array $overrides = []): array
{
    return [
        'name' => 'Nguyễn Văn Tư Vấn',
        'email' => 'tuvan@truong.edu.vn',
        'phone' => '0912 345 678',
        'password' => 'matkhau-manh-123',
        'role' => 'counselor',
        'data_scope' => '',
        'is_active' => true,
        'team_ids' => [],
        ...$overrides,
    ];
}

describe('default roles', function () {
    it('seeds the 9 roles of docs/02-phan-quyen.md', function () {
        expect(Role::pluck('name')->sort()->values()->all())->toBe(collect(RoleDefinitions::roleCodes())->sort()->values()->all())
            ->and(Role::findByName('admin')->permissions)->toHaveCount(count(RoleDefinitions::permissions()));
    });

    it('gives each role the documented permissions', function () {
        $can = fn (string $role, string $permission) => Role::findByName($role)->hasPermissionTo($permission);

        expect($can('counselor', 'case.update'))->toBeTrue()
            ->and($can('counselor', 'case.assign'))->toBeFalse()
            ->and($can('counselor', 'report.view'))->toBeFalse()
            ->and($can('counselor', 'applicant.view_sensitive'))->toBeTrue()
            ->and($can('ambassador', 'applicant.view_sensitive'))->toBeFalse()
            ->and($can('ambassador', 'applicant.create'))->toBeTrue()
            ->and($can('team_lead', 'case.assign'))->toBeTrue()
            ->and($can('marketing', 'campaign.approve'))->toBeTrue()
            ->and($can('marketing', 'applicant.update'))->toBeFalse()
            ->and($can('admission_head', 'campaign.approve'))->toBeTrue()
            ->and($can('admission_head', 'user.create'))->toBeFalse()
            ->and($can('admission_clerk', 'application.update'))->toBeTrue()
            ->and($can('faculty_viewer', 'case.view'))->toBeFalse()
            ->and($can('school_officer', 'high_school.update'))->toBeTrue();
    });

    it('sets each role a default data scope', function () {
        expect(RoleDefinitions::defaultScope('admin'))->toBe(DataScopeLevel::All)
            ->and(RoleDefinitions::defaultScope('team_lead'))->toBe(DataScopeLevel::Territory)
            ->and(RoleDefinitions::defaultScope('counselor'))->toBe(DataScopeLevel::Own)
            ->and(RoleDefinitions::defaultScope('ambassador'))->toBe(DataScopeLevel::Own);
    });

    it('can be re-run and resets a default role to its definition', function () {
        Role::findByName('counselor')->givePermissionTo('user.delete');

        $this->seed(RoleSeeder::class);

        expect(Role::findByName('counselor')->hasPermissionTo('user.delete'))->toBeFalse()
            ->and(Role::count())->toBe(9);
    });
});

describe('screens', function () {
    it('forbids users without user.view', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');

        foreach (['/settings/users', '/settings/users/teams', '/settings/users/roles'] as $url) {
            $this->actingAs($counselor)->get($url)->assertForbidden();
        }
    });

    it('lists users of the organization only, with role, scope and teams', function () {
        $team = Team::factory()->for($this->org)->create(['name' => 'Nhóm Bắc']);
        $member = User::factory()->for($this->org)->create(['name' => 'An', 'data_scope' => DataScopeLevel::Team]);
        $member->assignRole('counselor');
        $member->teams()->attach($team);
        User::factory()->for($this->other)->create(['name' => 'Người tổ chức khác']);

        $this->actingAs($this->admin)
            ->get('/settings/users?sort=name')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->where('tab', 'users')
                ->has('tabs', 3)
                ->where('users.meta.total', 2)
                ->where('users.data.0.name', 'An')
                ->where('users.data.0.role_label', 'Cán bộ tư vấn')
                ->where('users.data.0.data_scope', 'team')
                ->where('users.data.0.teams_label', 'Nhóm Bắc')
                ->where('users.data.1.is_self', true)
                ->where('can.create', true)
                ->has('roleOptions', 9));
    });

    it('filters users by role, scope and active state', function () {
        User::factory()->for($this->org)->create(['is_active' => false])->assignRole('counselor');
        User::factory()->for($this->org)->create(['is_active' => true])->assignRole('marketing');

        $count = fn (string $query) => $this->actingAs($this->admin)->get("/settings/users?{$query}")->viewData('page')['props']['users']['meta']['total'];

        expect($count('filter[role]=counselor'))->toBe(1)
            ->and($count('filter[role]=counselor,marketing'))->toBe(2)
            ->and($count('filter[is_active]=0'))->toBe(1)
            ->and($count('filter[role]=hacker'))->toBe(3);
    });

    it('shows a viewer the lists without management actions', function () {
        $lead = User::factory()->for($this->org)->create();
        $lead->assignRole('team_lead'); // user.view, team.view only

        $this->actingAs($lead)->get('/settings/users')
            ->assertInertia(fn (Assert $page) => $page->where('can', ['create' => false, 'update' => false, 'delete' => false]));

        $this->actingAs($lead)->post('/settings/users', userPayload())->assertForbidden();
        $this->actingAs($lead)->post('/settings/teams', ['name' => 'Nhóm lén'])->assertForbidden();
    });

    it('shows the roles overview with user counts and grouped permissions', function () {
        $this->actingAs($this->admin)
            ->get('/settings/users/roles')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'roles')
                ->has('roles', 9)
                ->where('roles.0.code', 'admin')
                ->where('roles.0.users_count', 1)
                ->where('roles.0.scope_label', 'Toàn bộ')
                ->where('roles.3.code', 'counselor')
                ->where('roles.3.scope', 'own'));
    });
});

describe('users', function () {
    it('creates a user in the organization with role, default scope, teams and a hashed password', function () {
        $team = Team::factory()->for($this->org)->create();

        $this->actingAs($this->admin)
            ->post('/settings/users', userPayload(['email' => ' TuVan@Truong.edu.vn ', 'team_ids' => [$team->id]]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'tuvan@truong.edu.vn')->first();

        expect($user->organization_id)->toBe($this->org->id)
            ->and($user->hasRole('counselor'))->toBeTrue()
            ->and($user->data_scope)->toBe(DataScopeLevel::Own)
            ->and($user->teams->pluck('id')->all())->toBe([$team->id])
            ->and(Hash::check('matkhau-manh-123', $user->password))->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull();

        $log = Activity::where('description', 'Tạo người dùng')->first();
        expect($log->subject_id)->toBe($user->id)->and($log->causer_id)->toBe($this->admin->id);
    });

    it('validates the form in Vietnamese', function () {
        $this->actingAs($this->admin)
            ->post('/settings/users', userPayload(['name' => '', 'email' => 'khong-hop-le', 'phone' => '123', 'password' => '', 'role' => 'hacker']))
            ->assertSessionHasErrors([
                'name' => 'Vui lòng nhập họ tên.',
                'email' => 'Trường email phải là địa chỉ email hợp lệ.',
                'phone' => 'Số điện thoại không hợp lệ.',
                'password' => 'Vui lòng nhập mật khẩu.',
                'role' => 'Giá trị đã chọn của vai trò không hợp lệ.',
            ]);

        $this->actingAs($this->admin)->post('/settings/users', userPayload(['email' => $this->admin->email]))
            ->assertSessionHasErrors(['email' => 'email đã tồn tại.']);

        $foreignTeam = Team::factory()->for($this->other)->create();
        $this->actingAs($this->admin)->post('/settings/users', userPayload(['team_ids' => [$foreignTeam->id]]))
            ->assertSessionHasErrors('team_ids.0');
    });

    it('updates a user, keeps the password when blank and logs role and scope changes', function () {
        $user = User::factory()->for($this->org)->create(['password' => 'old-password-123']);
        $user->assignRole('counselor');
        $oldHash = $user->password;

        $this->actingAs($this->admin)
            ->put("/settings/users/{$user->id}", userPayload([
                'email' => $user->email, 'password' => '', 'role' => 'team_lead', 'data_scope' => 'team', 'name' => 'Tên mới',
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();

        expect($user->name)->toBe('Tên mới')
            ->and($user->hasRole('team_lead'))->toBeTrue()
            ->and($user->hasRole('counselor'))->toBeFalse()
            ->and($user->data_scope)->toBe(DataScopeLevel::Team)
            ->and($user->password)->toBe($oldHash);

        $role = Activity::where('description', 'Đổi vai trò')->first();
        expect($role->properties->all())->toBe(['from' => 'counselor', 'to' => 'team_lead'])
            ->and(Activity::where('description', 'Đổi phạm vi dữ liệu')->first()->properties['to'])->toBe('team');
    });

    it('cannot touch users of another organization', function () {
        $foreign = User::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->put("/settings/users/{$foreign->id}", userPayload(['email' => $foreign->email]))->assertNotFound();
        $this->actingAs($this->admin)->delete("/settings/users/{$foreign->id}")->assertNotFound();
    });

    it('only lets an administrator grant the administrator role', function () {
        $head = User::factory()->for($this->org)->create();
        $head->assignRole('admission_head');
        $head->givePermissionTo('user.create', 'user.update'); // a head allowed to manage users

        $this->actingAs($head)
            ->post('/settings/users', userPayload(['role' => 'admin']))
            ->assertSessionHasErrors(['role' => 'Chỉ quản trị hệ thống mới được cấp vai trò này.']);

        $this->actingAs($this->admin)
            ->post('/settings/users', userPayload(['role' => 'admin']))
            ->assertSessionHasNoErrors();
    });

    it('keeps at least one active administrator', function () {
        $this->actingAs($this->admin)
            ->put("/settings/users/{$this->admin->id}", userPayload(['email' => $this->admin->email, 'role' => 'counselor']))
            ->assertSessionHasErrors('role');

        $this->actingAs($this->admin)
            ->put("/settings/users/{$this->admin->id}", userPayload(['email' => $this->admin->email, 'role' => 'admin', 'is_active' => false]))
            ->assertSessionHasErrors('role');

        expect($this->admin->fresh()->hasRole('admin'))->toBeTrue()->and($this->admin->fresh()->is_active)->toBeTrue();

        // With a second administrator the first can step down.
        $second = User::factory()->for($this->org)->create();
        $second->assignRole('admin');

        $this->actingAs($this->admin)
            ->put("/settings/users/{$this->admin->id}", userPayload(['email' => $this->admin->email, 'role' => 'counselor']))
            ->assertSessionHasNoErrors();
    });

    it('soft deletes users but never yourself, and removes them from teams', function () {
        $team = Team::factory()->for($this->org)->create();
        $user = User::factory()->for($this->org)->create();
        $user->assignRole('counselor');
        $user->teams()->attach($team);

        $this->actingAs($this->admin)->delete("/settings/users/{$this->admin->id}")->assertForbidden();

        $this->actingAs($this->admin)->delete("/settings/users/{$user->id}")->assertRedirect();

        $this->assertSoftDeleted($user);
        expect($team->members()->count())->toBe(0)
            ->and(Activity::where('description', 'Xoá người dùng')->count())->toBe(1);
    });
});

describe('teams', function () {
    it('creates a team with leader and members, the leader counted as a member', function () {
        $leader = User::factory()->for($this->org)->create();
        $member = User::factory()->for($this->org)->create();

        $this->actingAs($this->admin)
            ->post('/settings/teams', ['name' => 'Nhóm Trung Bộ', 'leader_id' => $leader->id, 'member_ids' => [$member->id]])
            ->assertSessionHasNoErrors();

        $team = Team::where('name', 'Nhóm Trung Bộ')->first();

        expect($team->organization_id)->toBe($this->org->id)
            ->and($team->members->pluck('id')->sort()->values()->all())->toBe([$leader->id, $member->id]);

        $this->actingAs($this->admin)->get('/settings/users/teams')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'teams')
                ->where('teams.data.0.leader_name', $leader->name)
                ->where('teams.data.0.members_count', 2));
    });

    it('rejects duplicate names in the organization and users of other organizations', function () {
        Team::factory()->for($this->org)->create(['name' => 'Nhóm Nam']);
        Team::factory()->for($this->other)->create(['name' => 'Nhóm Bắc']);
        $foreigner = User::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/settings/teams', ['name' => 'Nhóm Nam'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/settings/teams', ['name' => 'Nhóm Bắc'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/settings/teams', ['name' => 'Nhóm lạ', 'leader_id' => $foreigner->id])->assertSessionHasErrors('leader_id');
    });

    it('updates and deletes a team of the organization only', function () {
        $team = Team::factory()->for($this->org)->create(['name' => 'Cũ']);
        $foreign = Team::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->put("/settings/teams/{$team->id}", ['name' => 'Mới', 'member_ids' => [$this->admin->id]])->assertSessionHasNoErrors();
        expect($team->fresh()->name)->toBe('Mới')->and($team->members()->count())->toBe(1);

        $this->actingAs($this->admin)->put("/settings/teams/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();

        $this->actingAs($this->admin)->delete("/settings/teams/{$team->id}")->assertRedirect();
        $this->assertSoftDeleted($team);
    });
});

describe('menu', function () {
    it('hides built screens from users without the permission', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor'); // catalog.view, no user.view

        $titles = fn (User $u) => collect($this->actingAs($u)->get('/dashboard')->viewData('page')['props']['navigation'])
            ->firstWhere('title', 'Cài đặt')['children'];

        expect(collect($titles($counselor))->pluck('title')->all())->toContain('Danh mục')->not->toContain('Người dùng & vai trò')
            ->and(collect($titles($this->admin))->pluck('title')->all())->toContain('Danh mục', 'Người dùng & vai trò');
    });
});
