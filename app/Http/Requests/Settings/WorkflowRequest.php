<?php

namespace App\Http\Requests\Settings;

use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Workflow\Enums\WorkflowActionType;
use App\Domain\Workflow\Enums\WorkflowObject;
use App\Domain\Workflow\Enums\WorkflowTrigger;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Services\SafeWebhookUrl;
use App\Domain\Workflow\Services\WorkflowSchema;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('id') ? Workflow::query()->findOrFail($this->route('id')) : null;

        return $workflow ? $this->user()->can('update', $workflow) : $this->user()->can('create', Workflow::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'object' => ['required', Rule::enum(WorkflowObject::class)],
            'trigger' => ['required', 'array'],
            'trigger.type' => ['required', Rule::enum(WorkflowTrigger::class)],
            'trigger.field' => ['nullable', 'string'],
            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', Rule::in(array_keys(WorkflowSchema::OPERATORS))],
            'conditions.*.value' => ['nullable'],
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', Rule::enum(WorkflowActionType::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v): void {
            $object = WorkflowObject::tryFrom((string) $this->input('object'));

            if ($object === null) {
                return;
            }

            $schema = app(WorkflowSchema::class);
            $fields = $schema->fields($object);

            if ($this->input('trigger.type') === 'updated' && ! in_array($this->input('trigger.field'), $schema->watchable($object), true)) {
                $v->errors()->add('trigger.field', 'Chọn trường cần theo dõi.');
            }

            if ($this->input('trigger.type') === 'stage_changed' && $object !== WorkflowObject::AdmissionCase) {
                $v->errors()->add('trigger.type', 'Chỉ hồ sơ tuyển sinh mới có giai đoạn.');
            }

            foreach ((array) $this->input('conditions', []) as $i => $c) {
                if (! isset($fields[$c['field'] ?? ''])) {
                    $v->errors()->add("conditions.$i.field", 'Trường điều kiện không hợp lệ.');
                } elseif (! in_array($c['operator'] ?? '', ['empty', 'not_empty'], true) && ($c['value'] ?? '') === '') {
                    $v->errors()->add("conditions.$i.value", 'Nhập giá trị so sánh.');
                }
            }

            foreach ((array) $this->input('actions', []) as $i => $a) {
                $this->checkAction($v, $object, $i, (array) $a);
            }
        }];
    }

    /** @param  array<string, mixed>  $a */
    private function checkAction(Validator $v, WorkflowObject $object, int $i, array $a): void
    {
        $type = WorkflowActionType::tryFrom((string) ($a['type'] ?? ''));

        if ($type === null) {
            return;
        }

        if (! $type->supports($object)) {
            $v->errors()->add("actions.$i.type", 'Hành động này không dùng được cho đối tượng đã chọn.');

            return;
        }

        $error = match ($type) {
            WorkflowActionType::Assign => ! empty($a['user_id']) && ! User::query()->whereKey($a['user_id'])->exists() ? 'Người được giao không tồn tại.' : null,
            WorkflowActionType::CreateTask => blank($a['title'] ?? null) ? 'Nhập tên việc.' : (! is_numeric($a['due_in_hours'] ?? 24) || $a['due_in_hours'] < 0 || $a['due_in_hours'] > 720 ? 'Hạn việc tối đa 720 giờ.' : null),
            WorkflowActionType::SendMessage => ! MessageTemplate::query()->whereKey($a['template_id'] ?? 0)->where('is_active', true)->exists() ? 'Chọn mẫu tin đang dùng.' : null,
            WorkflowActionType::UpdateField => ! in_array($a['field'] ?? '', WorkflowSchema::SETTABLE, true) ? 'Trường không được phép cập nhật.' : null,
            WorkflowActionType::Webhook => app(SafeWebhookUrl::class)->check((string) ($a['url'] ?? '')),
        };

        if ($error !== null) {
            $v->errors()->add("actions.$i.type", $error);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tên', 'object' => 'đối tượng', 'trigger.type' => 'sự kiện', 'actions' => 'hành động'];
    }
}
