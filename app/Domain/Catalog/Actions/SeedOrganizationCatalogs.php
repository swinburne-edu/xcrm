<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\LeadChannel;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Default lead sources, lost reasons and call results for one organization
 * (docs/04-database.md §8). Idempotent: existing rows are kept, missing ones added —
 * an organization's own edits (renames, deactivation) are never overwritten.
 */
class SeedOrganizationCatalogs
{
    /** code => [name, channel] */
    public const LEAD_SOURCES = [
        'website' => ['Website', LeadChannel::Online],
        'facebook_ads' => ['Facebook Ads', LeadChannel::Online],
        'tiktok_ads' => ['TikTok Ads', LeadChannel::Online],
        'google_ads' => ['Google Ads', LeadChannel::Online],
        'zalo_oa' => ['Zalo OA', LeadChannel::Online],
        'hotline' => ['Hotline', LeadChannel::Offline],
        'school_counseling' => ['Tư vấn tại trường THPT', LeadChannel::Offline],
        'open_day' => ['Ngày hội tuyển sinh', LeadChannel::Offline],
        'campus_tour' => ['Tham quan campus', LeadChannel::Offline],
        'referral' => ['Giới thiệu (cựu SV/SV)', LeadChannel::Referral],
        'ambassador' => ['Cộng tác viên', LeadChannel::Referral],
        'import' => ['Import', null],
    ];

    /** @var list<string> in display order */
    public const LOST_REASONS = [
        'Trúng tuyển trường khác',
        'Không đủ điểm',
        'Học phí cao',
        'Ngành không phù hợp',
        'Xa nhà',
        'Không liên lạc được',
        'Đi du học',
        'Học nghề/đi làm',
        'Khác',
    ];

    /** name => is_contacted, in display order */
    public const CALL_RESULTS = [
        'Không nghe máy' => false,
        'Thuê bao/tắt máy' => false,
        'Sai số' => false,
        'Hẹn gọi lại' => true,
        'Quan tâm' => true,
        'Không quan tâm' => true,
    ];

    public function __construct(private readonly CurrentOrganization $current) {}

    public function handle(Organization $organization): void
    {
        $this->current->run($organization->id, function () use ($organization): void {
            DB::transaction(function () use ($organization): void {
                foreach (self::LEAD_SOURCES as $code => [$name, $channel]) {
                    LeadSource::query()->firstOrCreate(
                        ['organization_id' => $organization->id, 'code' => $code],
                        ['name' => $name, 'channel' => $channel, 'is_active' => true],
                    );
                }

                foreach (self::LOST_REASONS as $index => $name) {
                    LostReason::query()->firstOrCreate(
                        ['organization_id' => $organization->id, 'name' => $name],
                        ['sort_order' => ($index + 1) * 10, 'is_active' => true],
                    );
                }

                $order = 0;

                foreach (self::CALL_RESULTS as $name => $isContacted) {
                    CallResult::query()->firstOrCreate(
                        ['organization_id' => $organization->id, 'name' => $name],
                        ['is_contacted' => $isContacted, 'sort_order' => (++$order) * 10],
                    );
                }
            });
        });
    }
}
