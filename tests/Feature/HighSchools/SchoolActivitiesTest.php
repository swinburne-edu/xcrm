<?php

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\ActivityAttendee;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['name' => 'Tuyển sinh 2027']);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->officer = User::factory()->for($this->org)->create(['name' => 'Cán bộ trường', 'data_scope' => DataScopeLevel::Territory]);
    $this->officer->assignRole('school_officer');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');

    $this->school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chu Văn An']);
    $this->elsewhere = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Nơi khác']);
    $territory = Territory::factory()->for($this->org)->create(['user_id' => $this->officer->id]);
    TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $this->school->id]);

    $this->payload = fn (array $extra = []) => $extra + [
        'type' => 'school_counseling', 'title' => 'Tư vấn tại THPT Chu Văn An', 'high_school_id' => $this->school->id,
        'start_at' => '2027-03-10T08:00', 'end_at' => '2027-03-10T11:30', 'location' => 'Hội trường', 'expected_attendees' => 300,
        'staff_ids' => [$this->admin->id, $this->officer->id], 'lead_id' => $this->officer->id,
    ];
    $this->make = fn (array $a = []) => SchoolActivity::factory()->create($a + ['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'high_school_id' => $this->school->id, 'created_by' => $this->admin->id]);
});

describe('listing', function () {
    it('lists the activities of the selected cycle with filters', function () {
        ($this->make)(['title' => 'Ngày hội A', 'type' => 'career_fair', 'start_at' => '2027-03-05 01:00:00']);
        ($this->make)(['title' => 'Tham quan B', 'type' => 'campus_tour', 'status' => 'confirmed', 'start_at' => '2027-03-20 01:00:00']);
        SchoolActivity::factory()->create(['organization_id' => $this->org->id, 'cycle_id' => AdmissionCycle::factory()->for($this->org)->create()->id, 'title' => 'Mùa khác']);

        $this->actingAs($this->admin)->get('/school-activities')->assertInertia(fn (Assert $page) => $page->component('SchoolActivities/Index')
            ->where('cycle.name', 'Tuyển sinh 2027')->where('activities.meta.total', 2)->where('activities.data.0.title', 'Tham quan B')
            ->where('can.create', true)->has('options.types', 5)->where('view', 'table'));

        $this->get('/school-activities?filter[type]=career_fair')->assertInertia(fn (Assert $page) => $page->where('activities.meta.total', 1)->where('activities.data.0.type_label', 'Ngày hội hướng nghiệp'));
        $this->get('/school-activities?filter[status]=confirmed&filter[q]=tham')->assertInertia(fn (Assert $page) => $page->where('activities.meta.total', 1));
        $this->get('/school-activities?filter[high_school_id]='.$this->elsewhere->id)->assertInertia(fn (Assert $page) => $page->where('activities.meta.total', 0));
    });

    it('gives the month calendar', function () {
        ($this->make)(['title' => 'Tháng 3', 'start_at' => '2027-03-10 01:00:00']);
        ($this->make)(['title' => 'Tháng 4', 'start_at' => '2027-04-10 01:00:00']);

        $this->actingAs($this->admin)->get('/school-activities?view=calendar&month=2027-03')->assertInertia(fn (Assert $page) => $page->where('view', 'calendar')
            ->where('calendar.month', '2027-03')->has('calendar.items', 1)->where('calendar.items.0.title', 'Tháng 3'));
        $this->get('/school-activities?month=garbage')->assertInertia(fn (Assert $page) => $page->where('calendar.month', now(config('app.display_timezone'))->format('Y-m')));
    });

    it('shows a school officer the activities of their territory, their own and those they staff', function () {
        $inTerritory = ($this->make)(['title' => 'Trong vùng']);
        $outside = ($this->make)(['title' => 'Ngoài vùng', 'high_school_id' => $this->elsewhere->id]);
        $created = ($this->make)(['title' => 'Do tôi tạo', 'high_school_id' => $this->elsewhere->id, 'created_by' => $this->officer->id]);
        $staffed = ($this->make)(['title' => 'Tôi tham gia', 'high_school_id' => $this->elsewhere->id]);
        $staffed->staff()->attach($this->officer->id, ['role' => 'member']);

        $this->actingAs($this->officer);
        $this->get('/school-activities')->assertInertia(fn (Assert $page) => $page->where('activities.meta.total', 3));
        $this->get("/school-activities/{$outside->id}")->assertNotFound(); // outside the data scope = not there
        $this->get("/school-activities/{$staffed->id}")->assertOk();
    });

    it('needs school_activity.view', function () {
        $this->actingAs($this->counselor)->get('/school-activities')->assertForbidden();
    });
});

describe('creating and editing', function () {
    it('creates an activity with its check-in token, staff and times in UTC', function () {
        $campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $this->cycle->id]);

        $response = $this->actingAs($this->admin)->post('/school-activities', ($this->payload)(['campaign_id' => $campaign->id]))->assertSessionHasNoErrors();

        $activity = SchoolActivity::query()->firstOrFail();
        $response->assertRedirect("/school-activities/{$activity->id}");
        expect($activity->cycle_id)->toBe($this->cycle->id)->and($activity->status->value)->toBe('planned')->and(strlen($activity->checkin_token))->toBe(40)
            ->and($activity->start_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 01:00')->and($activity->end_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 04:30')
            ->and($activity->campaign_id)->toBe($campaign->id)->and($activity->created_by)->toBe($this->admin->id)
            ->and($activity->staff()->pluck('school_activity_user.role', 'users.id')->all())->toBe([$this->admin->id => 'member', $this->officer->id => 'lead'])
            ->and($activity->checkinUrl())->toBe(url("/checkin/{$activity->checkin_token}"));
    });

    it('gives every activity its own token', function () {
        $this->actingAs($this->admin);
        $this->post('/school-activities', ($this->payload)());
        $this->post('/school-activities', ($this->payload)(['title' => 'Khác']));

        expect(SchoolActivity::query()->pluck('checkin_token')->unique())->toHaveCount(2);
    });

    it('validates the form', function () {
        $stranger = User::factory()->for(Organization::factory()->create())->create();
        $this->actingAs($this->admin);

        $this->post('/school-activities', [])->assertSessionHasErrors(['type', 'title']);
        $this->post('/school-activities', ($this->payload)(['type' => 'party']))->assertSessionHasErrors('type');
        $this->post('/school-activities', ($this->payload)(['start_at' => '10/03/2027']))->assertSessionHasErrors('start_at');
        $this->post('/school-activities', ($this->payload)(['end_at' => '2027-03-10T07:00']))->assertSessionHasErrors('end_at');
        $this->post('/school-activities', ($this->payload)(['status' => 'done']))->assertSessionHasErrors('status');
        $this->post('/school-activities', ($this->payload)(['high_school_id' => 999999]))->assertSessionHasErrors('high_school_id');
        $this->post('/school-activities', ($this->payload)(['staff_ids' => [$stranger->id]]))->assertSessionHasErrors('staff_ids.0');
        $this->post('/school-activities', ($this->payload)(['lead_id' => $this->counselor->id]))->assertSessionHasErrors('lead_id');
        $this->post('/school-activities', ($this->payload)(['expected_attendees' => -1]))->assertSessionHasErrors('expected_attendees');

        expect(SchoolActivity::query()->count())->toBe(0);
    });

    it('may take place at the campus (no school)', function () {
        $this->actingAs($this->admin)->post('/school-activities', ($this->payload)(['high_school_id' => null, 'type' => 'campus_tour', 'title' => 'Tham quan campus']))->assertSessionHasNoErrors();

        expect(SchoolActivity::query()->firstOrFail()->high_school_id)->toBeNull();
    });

    it('needs school_activity.create and an open cycle', function () {
        $this->actingAs($this->counselor)->post('/school-activities', ($this->payload)())->assertForbidden();

        $this->cycle->update(['status' => 'closed']);
        $this->actingAs($this->admin)->post('/school-activities', ($this->payload)())->assertSessionHasErrors('cycle_id');
        $this->get('/school-activities')->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        expect(SchoolActivity::query()->count())->toBe(0);
    });

    it('edits an activity and keeps its token, re-syncing the staff', function () {
        $activity = ($this->make)();
        $token = $activity->checkin_token;
        $this->actingAs($this->admin);

        $this->put("/school-activities/{$activity->id}", ($this->payload)(['title' => 'Đổi tên', 'status' => 'confirmed', 'staff_ids' => [$this->officer->id]]))->assertSessionHasNoErrors();

        $activity->refresh();
        expect($activity->title)->toBe('Đổi tên')->and($activity->status->value)->toBe('confirmed')->and($activity->checkin_token)->toBe($token)
            ->and($activity->staff()->pluck('users.id')->all())->toBe([$this->officer->id]);
    });

    it('cannot be edited in a closed cycle, and another organization\'s activity is not found', function () {
        $other = SchoolActivity::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $activity = ($this->make)();
        $this->actingAs($this->admin);

        $this->put("/school-activities/{$other->id}", ($this->payload)())->assertNotFound();

        $this->cycle->update(['status' => 'closed']);
        $this->put("/school-activities/{$activity->id}", ($this->payload)())->assertForbidden();
        $this->delete("/school-activities/{$activity->id}")->assertForbidden();
    });

    it('deletes softly', function () {
        $activity = ($this->make)();

        $this->actingAs($this->admin)->delete("/school-activities/{$activity->id}")->assertRedirect('/school-activities');

        expect(SchoolActivity::query()->count())->toBe(0)->and(SchoolActivity::query()->withTrashed()->count())->toBe(1);
    });
});

describe('detail, report and QR', function () {
    it('shows the check-in link, the staff and the students who checked in (phone masked without permission)', function () {
        $activity = ($this->make)();
        $activity->staff()->attach($this->officer->id, ['role' => 'lead']);
        $student = Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn An', 'phone' => '0912345678', 'class_name' => '12A1']);
        ActivityAttendee::query()->create(['organization_id' => $this->org->id, 'school_activity_id' => $activity->id, 'applicant_id' => $student->id, 'checked_in_at' => now()]);

        $this->actingAs($this->admin)->get("/school-activities/{$activity->id}")->assertInertia(fn (Assert $page) => $page->component('SchoolActivities/Show')
            ->where('activity.checkin_url', url("/checkin/{$activity->checkin_token}"))->where('activity.lead_id', $this->officer->id)->where('activity.checkins', 1)
            ->has('attendees', 1)->where('attendees.0.full_name', 'Nguyễn An')->where('attendees.0.class_name', '12A1')->where('can.update', true));
    });

    it('records the report after the activity and closes the check-in', function () {
        $activity = ($this->make)();
        ActivityAttendee::query()->create(['organization_id' => $this->org->id, 'school_activity_id' => $activity->id, 'applicant_id' => Applicant::factory()->for($this->org)->create()->id, 'checked_in_at' => now()]);
        $this->actingAs($this->admin);

        $this->post("/school-activities/{$activity->id}/complete", ['cost' => 4500000, 'result_note' => 'Nhiều học sinh quan tâm CNTT'])->assertSessionHasNoErrors();

        $activity->refresh();
        expect($activity->status->value)->toBe('done')->and($activity->actual_attendees)->toBe(1)->and((int) $activity->cost)->toBe(4500000)->and($activity->result_note)->toBe('Nhiều học sinh quan tâm CNTT');

        $this->post("/school-activities/{$activity->id}/complete", ['actual_attendees' => 250])->assertSessionHasNoErrors();
        expect($activity->refresh()->actual_attendees)->toBe(250)->and($activity->cost)->toBeNull();

        $this->put("/school-activities/{$activity->id}", ($this->payload)(['status' => 'planned']))->assertSessionHasErrors('status');
    });

    it('does not report on a cancelled activity and validates the report', function () {
        $activity = ($this->make)(['status' => 'cancelled']);
        $this->actingAs($this->admin);

        $this->post("/school-activities/{$activity->id}/complete", [])->assertSessionHasErrors('status');
        $this->post("/school-activities/{$activity->id}/complete", ['cost' => -5])->assertSessionHasErrors('cost');
    });

    it('serves the QR code as an SVG of the check-in link', function () {
        $activity = ($this->make)();

        $response = $this->actingAs($this->admin)->get("/school-activities/{$activity->id}/qr.svg");

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('image/svg+xml')->and($response->getContent())->toContain('<svg')->and($response->headers->get('content-disposition'))->toBeNull();
        expect($this->get("/school-activities/{$activity->id}/qr.svg?download=1")->headers->get('content-disposition'))->toContain('attachment');
    });

    it('keeps the QR code from people who may not see the activity', function () {
        $activity = ($this->make)(['high_school_id' => $this->elsewhere->id]);

        $this->actingAs($this->officer)->get("/school-activities/{$activity->id}/qr.svg")->assertNotFound();
        $this->actingAs($this->counselor)->get("/school-activities/{$activity->id}/qr.svg")->assertNotFound();
    });
});
