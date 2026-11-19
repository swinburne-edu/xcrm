<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Communication\Actions\SaveMessageTemplate;
use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MessageTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /settings/message-templates (task 3.1): reusable messages with {{variables}}. Authorization: MessageTemplatePolicy. */
class MessageTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MessageTemplate::class);

        return Inertia::render('Messages/Templates', [
            'templates' => MessageTemplate::query()->orderBy('channel')->orderBy('name')->get()->map(fn (MessageTemplate $t) => [
                'id' => $t->id,
                'channel' => $t->channel->value,
                'channel_label' => $t->channel->label(),
                'name' => $t->name,
                'subject' => $t->subject,
                'body' => $t->body,
                'provider_template_id' => $t->provider_template_id,
                'is_active' => $t->is_active,
            ])->all(),
            'options' => [
                'channels' => array_map(fn (MessageChannelType $c) => ['value' => $c->value, 'label' => $c->label()], MessageChannelType::cases()),
                'variables' => collect(TemplateRenderer::VARIABLES)->map(fn ($label, $name) => ['name' => $name, 'label' => $label])->values()->all(),
            ],
            'can' => ['manage' => $request->user()->can('create', MessageTemplate::class)],
        ]);
    }

    public function store(MessageTemplateRequest $request, SaveMessageTemplate $save): RedirectResponse
    {
        $template = $save->handle(null, $request->validated());

        return $this->toast("Đã tạo mẫu {$template->name}.");
    }

    public function update(MessageTemplateRequest $request, int $id, SaveMessageTemplate $save): RedirectResponse
    {
        $template = MessageTemplate::query()->findOrFail($id);
        $save->handle($template, $request->validated());

        return $this->toast("Đã cập nhật mẫu {$template->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $template = MessageTemplate::query()->findOrFail($id);
        $this->authorize('delete', $template);
        $template->delete();

        return $this->toast("Đã xoá mẫu {$template->name}.");
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
