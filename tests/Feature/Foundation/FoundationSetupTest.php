<?php

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Team;
use App\Domain\Shared\Concerns\HasCustomData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

it('uses Vietnamese locale and stores time in UTC', function () {
    expect(app()->getLocale())->toBe('vi')
        ->and(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Asia/Ho_Chi_Minh');
});

it('shows validation messages in Vietnamese', function () {
    $validator = Validator::make(['email' => 'abc'], ['name' => 'required', 'email' => 'email']);

    expect($validator->errors()->first('name'))->toBe('Vui lòng nhập họ tên.')
        ->and($validator->errors()->first('email'))->toBe('Trường email phải là địa chỉ email hợp lệ.');
});

it('shows the login failure message in Vietnamese', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
});

it('shares locale and display timezone with Inertia pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'vi')
            ->where('timezone', 'Asia/Ho_Chi_Minh'));
});

it('uses short morph names', function () {
    expect((new User)->getMorphClass())->toBe('user')
        ->and((new Team)->getMorphClass())->toBe('team')
        ->and(Relation::getMorphedModel('organization'))->not->toBeNull();
});

it('defaults new users to the own data scope', function () {
    $user = User::factory()->create();

    expect($user->fresh()->data_scope)->toBe(DataScopeLevel::Own)
        ->and($user->fresh()->is_active)->toBeTrue()
        ->and(DataScopeLevel::Territory->label())->toBe('Theo vùng phụ trách');
});

it('creates the common columns with the schema macros', function () {
    expect(Schema::hasColumns('teams', [
        'organization_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('users', [
            'organization_id', 'phone', 'avatar_path', 'is_active', 'data_scope', 'last_login_at', 'deleted_at',
        ]))->toBeTrue();
});

describe('HasCustomData', function () {
    beforeEach(function () {
        Schema::create('custom_data_fixtures', function (Blueprint $table) {
            $table->id();
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        $this->model = new class extends Model
        {
            use HasCustomData;

            protected $table = 'custom_data_fixtures';

            protected $guarded = [];
        };
    });

    it('reads and writes custom fields', function () {
        $record = $this->model->newInstance();
        $record->setCustom('facebook_group', 'K12 Nghệ An')->setCustom('siblings', 2)->save();

        $fresh = $record->fresh();

        expect($fresh->custom_data)->toBe(['facebook_group' => 'K12 Nghệ An', 'siblings' => 2])
            ->and($fresh->getCustom('siblings'))->toBe(2)
            ->and($fresh->getCustom('missing', 'mặc định'))->toBe('mặc định');

        $fresh->forgetCustom('siblings')->save();

        expect($fresh->fresh()->custom_data)->toBe(['facebook_group' => 'K12 Nghệ An']);
    });

    it('filters by a custom field', function () {
        $this->model->newInstance()->setCustom('siblings', 2)->save();
        $this->model->newInstance()->setCustom('siblings', 0)->save();

        expect($this->model->newQuery()->whereCustom('siblings', 2)->count())->toBe(1)
            ->and($this->model->newQuery()->whereCustom('siblings', '>', 1)->count())->toBe(1);
    });

    it('rejects keys that are not snake_case', function (string $key) {
        $this->model->newInstance()->setCustom($key, 'x');
    })->with(['Bad-Key', "a') or 1=1 --", '1abc', ''])
        ->throws(InvalidArgumentException::class);
});
