<?php

namespace App\Http\Requests\Settings;

use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Services\TemplateRenderer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('id') ? MessageTemplate::query()->findOrFail($this->route('id')) : null;

        return $template ? $this->user()->can('update', $template) : $this->user()->can('create', MessageTemplate::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(MessageChannelType::class)],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'provider_template_id' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v): void {
            $renderer = app(TemplateRenderer::class);
            $unknown = $renderer->unknown(($this->input('subject') ?? '').' '.($this->input('body') ?? ''));

            if ($unknown !== []) {
                $v->errors()->add('body', 'Biến không có: '.implode(', ', array_map(fn ($n) => '{{'.$n.'}}', $unknown)).'.');
            }

            if ($this->input('channel') === MessageChannelType::Zns->value && blank($this->input('provider_template_id'))) {
                $v->errors()->add('provider_template_id', 'Mẫu Zalo ZNS cần mã mẫu đã được Zalo duyệt.');
            }

            if ($this->input('channel') === MessageChannelType::Email->value && blank($this->input('subject'))) {
                $v->errors()->add('subject', 'Email cần tiêu đề.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['channel' => 'kênh', 'name' => 'tên mẫu', 'subject' => 'tiêu đề', 'body' => 'nội dung', 'provider_template_id' => 'mã mẫu ZNS'];
    }
}
