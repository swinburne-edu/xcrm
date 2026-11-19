<?php

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Models\Organization;
use App\Domain\Portal\Models\PortalOtp;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.4, login: the SMS code, its limits and the registration of a new phone.
 * Module under test: OtpService, AuthController and PortalContext. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Đại học Demo']);
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    AdmissionCycle::factory()->for($this->org)->current()->create();
    config(['portal.organization_id' => $this->org->id, 'communication.drivers.sms' => 'http', 'communication.sms.url' => 'https://sms.test/send']);
    Http::fake(['sms.test/*' => Http::response(['id' => 'm1'])]);

    $this->code = function (): string {
        preg_match('/\b(\d{6})\b/', Http::recorded()->last()[0]['content'] ?? '', $m);

        return $m[1] ?? '';
    };
});

it('logs a known applicant in with the SMS code, once', function () {
    $applicant = Applicant::factory()->for($this->org)->create(['phone' => '0912345678']);

    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertOk();
    $code = ($this->code)();
    expect($code)->toHaveLength(6)->and(PortalOtp::query()->first()->code_hash)->not->toContain($code);

    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => $code])->assertOk()->assertJson(['needs_name' => false]);
    $this->get('/cong-thi-sinh')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Portal/Home')->where('applicant.full_name', $applicant->full_name));

    $this->post('/cong-thi-sinh/dang-xuat')->assertRedirect('/cong-thi-sinh/dang-nhap');
    $this->get('/cong-thi-sinh')->assertRedirect('/cong-thi-sinh/dang-nhap');
    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => $code])->assertUnprocessable();
});

it('registers a new phone after the code is checked', function () {
    $this->postJson('/cong-thi-sinh/dang-ky', ['full_name' => 'Chưa xác thực'])->assertUnprocessable();

    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0987654321'])->assertOk();
    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0987654321', 'code' => ($this->code)()])->assertOk()->assertJson(['needs_name' => true]);
    $this->get('/cong-thi-sinh')->assertRedirect('/cong-thi-sinh/dang-nhap');

    $this->postJson('/cong-thi-sinh/dang-ky', ['full_name' => 'Phạm Thu'])->assertOk();
    $this->get('/cong-thi-sinh')->assertOk()->assertInertia(fn (Assert $p) => $p->where('applicant.full_name', 'Phạm Thu'));
});

it('limits codes: resend delay, attempts, expiry and per day', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertOk();
    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertUnprocessable()->assertJsonValidationErrors('phone');

    $code = ($this->code)();

    foreach (range(1, 5) as $i) {
        $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => '000000']);
    }

    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => $code])->assertUnprocessable();

    $this->travel(2)->minutes();
    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertOk();
    $this->travel(10)->minutes();
    $this->postJson('/cong-thi-sinh/xac-thuc', ['phone' => '0912345678', 'code' => ($this->code)()])->assertUnprocessable();

    config(['portal.otp.max_per_day' => 2]);
    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertUnprocessable();
    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '123'])->assertUnprocessable();
});

it('reports a failed SMS and keeps no code behind', function () {
    config(['communication.sms.url' => 'https://bad.test/send']);
    Http::fake(['bad.test/*' => Http::response('x', 500)]);

    $this->postJson('/cong-thi-sinh/ma-xac-thuc', ['phone' => '0912345678'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    expect(PortalOtp::query()->count())->toBe(0);
});
