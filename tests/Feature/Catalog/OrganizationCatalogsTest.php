<?php

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Enums\TagColor;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Concerns\HasTags;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->manager = User::factory()->for($this->org)->create();
    $this->manager->givePermissionTo('catalog.manage');
});

it('seeds the default sources, lost reasons and call results of an organization', function () {
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $sources = LeadSource::acrossOrganizations()->where('organization_id', $this->org->id)->get();

    expect($sources)->toHaveCount(12)
        ->and($sources->firstWhere('code', 'school_counseling')->name)->toBe('Tư vấn tại trường THPT')
        ->and($sources->firstWhere('code', 'facebook_ads')->channel->value)->toBe('online')
        ->and($sources->firstWhere('code', 'import')->channel)->toBeNull()
        ->and(LostReason::acrossOrganizations()->where('organization_id', $this->org->id)->count())->toBe(9)
        ->and(LostReason::acrossOrganizations()->where('organization_id', $this->org->id)->orderBy('sort_order')->value('name'))
        ->toBe('Trúng tuyển trường khác');

    $calls = CallResult::acrossOrganizations()->where('organization_id', $this->org->id)->orderBy('sort_order')->get();

    expect($calls->pluck('name')->all())->toBe(['Không nghe máy', 'Thuê bao/tắt máy', 'Sai số', 'Hẹn gọi lại', 'Quan tâm', 'Không quan tâm'])
        ->and($calls->pluck('is_contacted')->all())->toBe([false, false, false, true, true, true]);
});

it('can be run again without duplicating or overwriting the organization edits', function () {
    $seed = app(SeedOrganizationCatalogs::class);
    $seed->handle($this->org);

    LeadSource::acrossOrganizations()->where('organization_id', $this->org->id)->where('code', 'website')
        ->update(['name' => 'Trang web trường', 'is_active' => false]);

    $seed->handle($this->org);

    expect(LeadSource::acrossOrganizations()->where('organization_id', $this->org->id)->count())->toBe(12)
        ->and(LostReason::acrossOrganizations()->where('organization_id', $this->org->id)->count())->toBe(9)
        ->and(CallResult::acrossOrganizations()->where('organization_id', $this->org->id)->count())->toBe(6);

    $website = LeadSource::acrossOrganizations()->where('organization_id', $this->org->id)->where('code', 'website')->first();
    expect($website->name)->toBe('Trang web trường')->and($website->is_active)->toBeFalse();
});

it('keeps each organization catalogs separate', function () {
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    expect(LeadSource::acrossOrganizations()->count())->toBe(24);

    $this->actingAs($this->manager)
        ->get('/settings/catalogs/lead-sources')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.meta.total', 12)
            ->where('can.manage', true));
});

it('shows the four organization tabs after the system ones', function () {
    $this->actingAs($this->manager)
        ->get('/settings/catalogs')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tabs', 12)
            ->where('tabs.8', ['key' => 'lead-sources', 'title' => 'Nguồn thí sinh'])
            ->where('tabs.9.key', 'lost-reasons')
            ->where('tabs.10.key', 'call-results')
            ->where('tabs.11.key', 'tags'));
});

it('cannot edit or delete a row of another organization', function () {
    app(SeedOrganizationCatalogs::class)->handle($this->other);
    $foreign = LeadSource::acrossOrganizations()->where('organization_id', $this->other->id)->first();

    $this->actingAs($this->manager)
        ->put("/settings/catalogs/lead-sources/{$foreign->id}", ['code' => 'hacked', 'name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($this->manager)
        ->delete("/settings/catalogs/lead-sources/{$foreign->id}")
        ->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Hacked');
});

it('creates a lead source in the user organization with a code derived from the name', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lead-sources', ['name' => 'Facebook Ads', 'code' => '', 'channel' => 'online', 'is_active' => true])
        ->assertSessionHasNoErrors();

    $source = LeadSource::acrossOrganizations()->where('name', 'Facebook Ads')->first();

    expect($source->organization_id)->toBe($this->org->id)
        ->and($source->code)->toBe('facebook_ads');
});

it('validates lead source codes and uniqueness inside the organization only', function () {
    LeadSource::factory()->for($this->org)->create(['code' => 'zalo']);
    LeadSource::factory()->for($this->other)->create(['code' => 'tiktok']);

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lead-sources', ['name' => 'Zalo khác', 'code' => 'zalo'])
        ->assertSessionHasErrors(['code' => 'mã đã tồn tại.']);

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lead-sources', ['name' => 'TikTok', 'code' => 'tiktok'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lead-sources', ['name' => 'Sai mã', 'code' => 'Sai Mã!'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lead-sources', ['name' => 'Kênh lạ', 'code' => 'kenh_la', 'channel' => 'telepathy'])
        ->assertSessionHasErrors('channel');
});

it('manages lost reasons and call results', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lost-reasons', ['name' => 'Học phí cao', 'sort_order' => 30, 'is_active' => true])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/lost-reasons', ['name' => 'Học phí cao'])
        ->assertSessionHasErrors(['name' => 'tên đã tồn tại.']);

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/call-results', ['name' => 'Hẹn gọi lại', 'is_contacted' => true, 'sort_order' => 40])
        ->assertSessionHasNoErrors();

    $reason = LostReason::where('name', 'Học phí cao')->first();
    $call = CallResult::where('name', 'Hẹn gọi lại')->first();

    expect($reason->organization_id)->toBe($this->org->id)
        ->and($reason->sort_order)->toBe(30)
        ->and($call->is_contacted)->toBeTrue();

    $this->actingAs($this->manager)
        ->put("/settings/catalogs/call-results/{$call->id}", ['name' => 'Hẹn gọi lại sau', 'is_contacted' => false, 'sort_order' => 45])
        ->assertSessionHasNoErrors();

    expect($call->fresh()->name)->toBe('Hẹn gọi lại sau')->and($call->fresh()->is_contacted)->toBeFalse();

    $this->actingAs($this->manager)->delete("/settings/catalogs/lost-reasons/{$reason->id}");
    expect(LostReason::find($reason->id))->toBeNull();
});

it('creates tags with a color and rejects unknown colors and duplicate names', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/tags', ['name' => 'Học sinh giỏi', 'color' => 'green'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/tags', ['name' => 'Học sinh giỏi', 'color' => 'blue'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/tags', ['name' => 'Màu lạ', 'color' => 'rainbow'])
        ->assertSessionHasErrors('color');

    $this->actingAs($this->manager)
        ->get('/settings/catalogs/tags')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.name', 'Học sinh giỏi')
            ->where('items.data.0.color', 'green')
            ->where('items.data.0.color_label', 'Xanh lá'));

    // The same name is fine in another organization.
    Tag::factory()->for($this->other)->create(['name' => 'Giới thiệu']);
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/tags', ['name' => 'Giới thiệu'])
        ->assertSessionHasNoErrors();
});

it('does not let a viewer change the organization catalogs', function () {
    $viewer = User::factory()->for($this->org)->create();
    $viewer->givePermissionTo('catalog.view');

    $this->actingAs($viewer)->post('/settings/catalogs/tags', ['name' => 'Không được'])->assertForbidden();
    $this->actingAs($viewer)->get('/settings/catalogs/tags')
        ->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
});

it('keeps tag colors in sync with the ToneBadge palette', function () {
    $frontend = file_get_contents(resource_path('js/components/crm/ToneBadge.vue'));

    foreach (TagColor::cases() as $color) {
        expect($frontend)->toContain("'{$color->value}'");
    }
});

describe('tags on records', function () {
    beforeEach(function () {
        Schema::create('taggable_fixtures', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        $this->fixtureClass = (new class extends Model
        {
            use HasTags;

            protected $table = 'taggable_fixtures';
        })::class;

        Relation::morphMap(['taggable_fixture' => $this->fixtureClass]);
    });

    it('attaches tags through the polymorphic pivot and removes links when a tag is deleted', function () {
        $record = new $this->fixtureClass;
        $record->save();

        $vip = Tag::factory()->for($this->org)->create(['name' => 'VIP']);
        $hot = Tag::factory()->for($this->org)->create(['name' => 'Nóng']);
        $record->tags()->attach([$vip->id, $hot->id]);

        expect($record->tags()->pluck('name')->sort()->values()->all())->toBe(['Nóng', 'VIP'])
            ->and(DB::table('taggables')->where('taggable_type', 'taggable_fixture')->count())->toBe(2);

        $this->actingAs($this->manager)->delete("/settings/catalogs/tags/{$vip->id}");

        expect(Tag::find($vip->id))->toBeNull()
            ->and($record->tags()->pluck('name')->all())->toBe(['Nóng']);
    });
});
