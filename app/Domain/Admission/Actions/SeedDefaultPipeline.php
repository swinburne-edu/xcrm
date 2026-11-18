<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * The default pipeline "Đại học chính quy" of an organization (docs/04-database.md §8).
 * Idempotent: an organization that already has a default pipeline for the level is left alone
 * (its own edits are never overwritten).
 */
class SeedDefaultPipeline
{
    public const NAME = 'Đại học chính quy';

    /** code => [name, type, probability, color, sla_hours] in order */
    public const STAGES = [
        'new' => ['Mới', StageType::Open, 5, 'gray', 24],
        'contacted' => ['Đã liên hệ', StageType::Open, 10, 'cyan', 72],
        'counseling' => ['Đang tư vấn', StageType::Open, 20, 'blue', null],
        'early_applied' => ['Nộp hồ sơ xét sớm', StageType::Open, 40, 'violet', null],
        'eligible' => ['Đủ điều kiện', StageType::Open, 55, 'violet', null],
        'moet_registered' => ['Đã đăng ký NV trên hệ thống Bộ', StageType::Open, 65, 'amber', null],
        'admitted' => ['Trúng tuyển', StageType::Open, 80, 'amber', null],
        'confirmed' => ['Xác nhận nhập học', StageType::Open, 95, 'green', null],
        'enrolled' => ['Nhập học', StageType::Won, 100, 'green', null],
        'lost' => ['Thất bại', StageType::Lost, 0, 'red', null],
    ];

    public function handle(Organization $organization): Pipeline
    {
        return DB::transaction(function () use ($organization): Pipeline {
            $existing = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('level', 'university')->where('is_default', true)->first();

            if ($existing !== null) {
                return $existing;
            }

            $pipeline = Pipeline::query()->create(['organization_id' => $organization->id, 'name' => self::NAME, 'level' => 'university', 'is_default' => true]);
            $order = 0;

            foreach (self::STAGES as $code => [$name, $type, $probability, $color, $sla]) {
                PipelineStage::query()->create([
                    'pipeline_id' => $pipeline->id, 'code' => $code, 'name' => $name, 'type' => $type,
                    'probability' => $probability, 'color' => $color, 'sort_order' => (++$order) * 10, 'sla_hours' => $sla,
                ]);
            }

            return $pipeline;
        });
    }
}
