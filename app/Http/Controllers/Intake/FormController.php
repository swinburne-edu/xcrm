<?php

namespace App\Http\Controllers\Intake;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Models\Form;
use App\Domain\Intake\Services\EmbedSnippet;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intake\IntakeFormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /forms: the web forms embedded on the university site. Each has a uuid, the fields it asks for, the lead source
 * / campaign it stamps on what comes in, and an embeddable HTML snippet (see EmbedSnippet).
 */
class FormController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('form.view'), 403);

        $forms = ListQuery::for(Form::query()->with(['campaign:id,name', 'source:id,name']), $request)
            ->filters(Filter::search('q', ['name']), Filter::boolean('is_active'))
            ->sorts(['name', 'created_at', 'is_active'])
            ->defaultSort('-created_at')
            ->toProps(fn (Form $f) => $this->row($f));

        return Inertia::render('Forms/Index', [
            'forms' => $forms,
            'options' => [
                'fields' => collect(Form::AVAILABLE_FIELDS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
                'sources' => LeadSource::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->all(),
                'campaigns' => Campaign::query()->orderByDesc('id')->limit(200)->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
            ],
            'can' => ['manage' => $request->user()->can('form.manage')],
        ]);
    }

    public function store(IntakeFormRequest $request): RedirectResponse
    {
        Form::query()->create($this->attributes($request) + ['organization_id' => $request->user()->organization_id]);

        return $this->toast('Đã tạo biểu mẫu.');
    }

    public function update(IntakeFormRequest $request, int $id): RedirectResponse
    {
        Form::query()->findOrFail($id)->update($this->attributes($request));

        return $this->toast('Đã cập nhật biểu mẫu.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('form.manage'), 403);
        Form::query()->findOrFail($id)->delete();

        return $this->toast('Đã xoá biểu mẫu.');
    }

    /** @return array<string, mixed> */
    private function attributes(IntakeFormRequest $request): array
    {
        $data = $request->validated();
        $selected = collect($data['fields'] ?? [])->keyBy('key');
        $fields = [['key' => 'full_name', 'required' => true], ['key' => 'phone', 'required' => true]];

        foreach (array_keys(Form::AVAILABLE_FIELDS) as $key) {
            if (! in_array($key, ['full_name', 'phone'], true) && $selected->has($key)) {
                $fields[] = ['key' => $key, 'required' => (bool) ($selected[$key]['required'] ?? false)];
            }
        }

        return [
            'name' => $data['name'],
            'campaign_id' => $data['campaign_id'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'success_message' => $data['success_message'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'fields' => $fields,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Form $f): array
    {
        return [
            'id' => $f->id,
            'uuid' => $f->uuid,
            'name' => $f->name,
            'campaign_id' => $f->campaign_id,
            'campaign_name' => $f->campaign?->name,
            'source_id' => $f->source_id,
            'source_name' => $f->source?->name,
            'fields' => $f->fields,
            'success_message' => $f->success_message,
            'is_active' => $f->is_active,
            'embed' => EmbedSnippet::for($f, url('/api/public/leads')),
        ];
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/forms');
    }
}
