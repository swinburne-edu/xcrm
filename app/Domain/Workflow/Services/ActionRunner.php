<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Activity\Actions\SaveTask;
use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Communication\Actions\SendMessages;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Workflow\Enums\WorkflowActionType;
use App\Domain\Workflow\Models\Workflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Runs one workflow action on a record and returns a short message for the run log; any problem is thrown
 * (the engine records it and carries on with the next action).
 */
class ActionRunner
{
    public function __construct(
        private readonly AssignCase $assign,
        private readonly SaveTask $saveTask,
        private readonly SendMessages $messages,
        private readonly SafeWebhookUrl $urls,
    ) {}

    /** @param  array<string, mixed>  $action */
    public function run(array $action, Model $subject, Workflow $workflow, string $event): string
    {
        return match (WorkflowActionType::from($action['type'])) {
            WorkflowActionType::Assign => $this->assign($action, $this->caseOf($subject)),
            WorkflowActionType::CreateTask => $this->task($action, $subject),
            WorkflowActionType::SendMessage => $this->message($action, $this->applicantOf($subject)),
            WorkflowActionType::UpdateField => $this->update($action, $this->caseOf($subject)),
            WorkflowActionType::Webhook => $this->webhook($action, $subject, $workflow, $event),
        };
    }

    /** @param  array<string, mixed>  $a */
    private function assign(array $a, AdmissionCase $case): string
    {
        $owner = null;

        if (! empty($a['user_id'])) {
            $owner = User::query()->whereKey($a['user_id'])->where('organization_id', $case->organization_id)->first()
                ?? throw new InvalidArgumentException('Người được giao không tồn tại.');
        }

        $this->assign->handle($case, $owner);

        return $owner === null ? 'Đưa về kho chung.' : "Giao cho {$owner->name}.";
    }

    /** @param  array<string, mixed>  $a */
    private function task(array $a, Model $subject): string
    {
        $assignee = ($a['assignee'] ?? 'owner') === 'owner' ? $this->ownerOf($subject) : User::query()->find($a['assignee']);

        if ($assignee === null) {
            throw new InvalidArgumentException('Không có người nhận việc (hồ sơ đang ở kho chung).');
        }

        $due = DisplayTime::toInput(now()->addHours(max(0, (int) ($a['due_in_hours'] ?? 24))));
        $this->saveTask->handle(null, ['title' => (string) $a['title'], 'assignee_id' => $assignee->id, 'due_at' => $due], $assignee, $subject);

        return 'Tạo việc "'.$a['title'].'" cho '.$assignee->name.'.';
    }

    /** @param  array<string, mixed>  $a */
    private function message(array $a, Applicant $applicant): string
    {
        $template = MessageTemplate::query()->findOrFail($a['template_id']);
        $result = $this->messages->handle($template, Applicant::query()->whereKey($applicant->id));

        if ($result['queued'] > 0) {
            return 'Đã xếp hàng gửi "'.$template->name.'".';
        }

        throw new InvalidArgumentException($result['no_consent'] > 0 ? 'Thí sinh chưa đồng ý nhận tin.' : 'Thí sinh thiếu số điện thoại hoặc email.');
    }

    /** @param  array<string, mixed>  $a */
    private function update(array $a, AdmissionCase $case): string
    {
        $field = (string) ($a['field'] ?? '');

        if (! in_array($field, WorkflowSchema::SETTABLE, true)) {
            throw new InvalidArgumentException("Không được cập nhật trường {$field}.");
        }

        $case->forceFill([$field => ($a['value'] ?? '') === '' ? null : $a['value']])->save();

        return "Cập nhật {$field}.";
    }

    /** @param  array<string, mixed>  $a */
    private function webhook(array $a, Model $subject, Workflow $workflow, string $event): string
    {
        $url = (string) ($a['url'] ?? '');

        if (($problem = $this->urls->check($url)) !== null) {
            throw new InvalidArgumentException($problem);
        }

        $response = Http::timeout(10)->acceptJson()->post($url, [
            'workflow' => $workflow->name,
            'event' => $event,
            'object' => $subject->getMorphClass(),
            'id' => $subject->getKey(),
            'uuid' => $subject->getAttribute('uuid'),
        ]);

        if ($response->failed()) {
            throw new InvalidArgumentException("Webhook trả lỗi {$response->status()}.");
        }

        return "Webhook trả {$response->status()}.";
    }

    private function caseOf(Model $subject): AdmissionCase
    {
        return $subject instanceof AdmissionCase ? $subject : throw new InvalidArgumentException('Hành động này chỉ dùng cho hồ sơ tuyển sinh.');
    }

    private function applicantOf(Model $subject): Applicant
    {
        return match (true) {
            $subject instanceof Applicant => $subject,
            $subject instanceof AdmissionCase => Applicant::query()->findOrFail($subject->applicant_id),
            default => throw new InvalidArgumentException('Không có thí sinh để gửi tin.'),
        };
    }

    private function ownerOf(Model $subject): ?User
    {
        $case = $subject instanceof AdmissionCase ? $subject : AdmissionCase::query()->where('applicant_id', $subject->getKey())->latest('cycle_id')->first();

        return $case?->owner_id ? User::query()->find($case->owner_id) : null;
    }
}
