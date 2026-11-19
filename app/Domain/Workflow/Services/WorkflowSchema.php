<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Enums\WorkflowObject;

/**
 * What a workflow may look at and change, per object. One whitelist drives validation, the engine and the builder UI.
 * type: number (compared as a number), text, bool, id (a record id, picked from a list in the UI).
 */
class WorkflowSchema
{
    public const OPERATORS = [
        'eq' => 'bằng',
        'neq' => 'khác',
        'gt' => 'lớn hơn',
        'lt' => 'nhỏ hơn',
        'empty' => 'để trống',
        'not_empty' => 'có giá trị',
    ];

    /** @return array<string, array{label: string, type: string, source?: string}> */
    public function fields(WorkflowObject $object): array
    {
        return match ($object) {
            WorkflowObject::AdmissionCase => [
                'stage_id' => ['label' => 'Giai đoạn', 'type' => 'id', 'source' => 'stages'],
                'status' => ['label' => 'Trạng thái', 'type' => 'text', 'source' => 'statuses'],
                'owner_id' => ['label' => 'Người phụ trách', 'type' => 'id', 'source' => 'users'],
                'source_id' => ['label' => 'Nguồn', 'type' => 'id', 'source' => 'sources'],
                'campaign_id' => ['label' => 'Chiến dịch', 'type' => 'id', 'source' => 'campaigns'],
                'temperature' => ['label' => 'Mức độ quan tâm', 'type' => 'text', 'source' => 'temperatures'],
                'primary_major_id' => ['label' => 'Ngành quan tâm chính', 'type' => 'id', 'source' => 'majors'],
                'score' => ['label' => 'Điểm lead', 'type' => 'number'],
            ],
            WorkflowObject::Applicant => [
                'province_id' => ['label' => 'Tỉnh/Thành phố', 'type' => 'id', 'source' => 'provinces'],
                'high_school_id' => ['label' => 'Trường THPT', 'type' => 'id', 'source' => 'high_schools'],
                'graduation_year' => ['label' => 'Năm tốt nghiệp', 'type' => 'number'],
                'current_grade' => ['label' => 'Lớp hiện tại', 'type' => 'number'],
                'first_source_id' => ['label' => 'Nguồn đầu tiên', 'type' => 'id', 'source' => 'sources'],
                'first_campaign_id' => ['label' => 'Chiến dịch đầu tiên', 'type' => 'id', 'source' => 'campaigns'],
                'consent_marketing' => ['label' => 'Đồng ý nhận tin', 'type' => 'bool'],
            ],
        };
    }

    /** Fields a trigger "updated" can watch. */
    public function watchable(WorkflowObject $object): array
    {
        return array_keys($this->fields($object));
    }

    /** Fields the action "update_field" may set (cases only). */
    public const SETTABLE = ['temperature', 'primary_major_id'];
}
