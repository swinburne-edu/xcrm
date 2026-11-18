<?php

namespace App\Http\Requests\Campaign;

use App\Domain\Campaign\Enums\CampaignChannel;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Enums\CampaignType;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CampaignRequest extends FormRequest
{
    private ?Campaign $campaign = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', Campaign::class);
        }

        return $this->campaign() !== null && (bool) $user?->can('update', $this->campaign());
    }

    private function campaign(): ?Campaign
    {
        return $this->campaign ??= Campaign::query()->with('cycle')->find($this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::lower(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $inOrganization = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $organizationId);
        $creating = $this->route('id') === null;

        return [
            'cycle_id' => [$creating ? 'required' : 'prohibited', 'integer', $inOrganization('admission_cycles')],
            'parent_id' => ['nullable', 'integer', $inOrganization('campaigns')],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('campaigns', 'code')
                ->where('organization_id', $organizationId)->ignore($this->route('id'))],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(CampaignType::class)],
            'channel' => ['nullable', Rule::enum(CampaignChannel::class)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'actual_cost' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'target_leads' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'target_applications' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'target_enrolled' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'landing_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'targets' => ['nullable', 'array'],
            'targets.majors' => ['nullable', 'array', 'max:200'],
            'targets.majors.*' => ['integer', $inOrganization('majors')],
            'targets.provinces' => ['nullable', 'array', 'max:100'],
            'targets.provinces.*' => ['integer', Rule::exists('provinces', 'id')],
            'targets.high_schools' => ['nullable', 'array', 'max:500'],
            'targets.high_schools.*' => ['integer', $inOrganization('high_schools')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $campaign = $this->campaign();
            $cycleId = $campaign?->cycle_id ?? (int) $this->input('cycle_id');

            if ($campaign === null && AdmissionCycle::query()->find($cycleId)?->isClosed()) {
                $validator->errors()->add('cycle_id', 'Mùa tuyển sinh đã đóng, không thể tạo chiến dịch.');
            }

            // utm_campaign is already in published links: fixed once the campaign leaves draft.
            if ($campaign !== null && $campaign->status !== CampaignStatus::Draft && $this->input('code') !== $campaign->code) {
                $validator->errors()->add('code', 'Mã chiến dịch (utm_campaign) không đổi được sau khi gửi duyệt.');
            }

            if ($this->filled('parent_id')) {
                $parent = Campaign::query()->find($this->input('parent_id'));

                if ($parent === null || $parent->cycle_id !== $cycleId) {
                    $validator->errors()->add('parent_id', 'Chiến dịch cha phải thuộc cùng mùa tuyển sinh.');
                } elseif ($campaign !== null && $this->isDescendantOrSelf($campaign, $parent)) {
                    $validator->errors()->add('parent_id', 'Không thể chọn chính nó hoặc chiến dịch con làm chiến dịch cha.');
                }
            }
        }];
    }

    private function isDescendantOrSelf(Campaign $campaign, Campaign $candidate): bool
    {
        for ($node = $candidate, $depth = 0; $node !== null && $depth < 20; $node = $node->parent, $depth++) {
            if ($node->id === $campaign->id) {
                return true;
            }
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã chiến dịch chỉ gồm chữ thường không dấu, số, gạch ngang và gạch dưới (dùng làm utm_campaign).',
            'code.unique' => 'Mã chiến dịch này đã được dùng.',
            'cycle_id.prohibited' => 'Không đổi được mùa tuyển sinh của chiến dịch.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cycle_id' => 'mùa tuyển sinh', 'parent_id' => 'chiến dịch cha', 'code' => 'mã chiến dịch', 'name' => 'tên chiến dịch',
            'type' => 'loại', 'channel' => 'kênh', 'start_date' => 'ngày bắt đầu', 'end_date' => 'ngày kết thúc',
            'budget' => 'ngân sách', 'actual_cost' => 'chi phí thực', 'owner_id' => 'người phụ trách', 'landing_url' => 'trang đích',
        ];
    }
}
