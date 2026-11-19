<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Platform\Actions\SaveCustomFieldDefinition;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Platform\Models\CustomFieldDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CustomFieldDefinitionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/custom-fields (task 2.5): the custom fields of each object. Deleting a definition hides the field
 * everywhere; values already saved stay in the records' custom_data and come back if a field with the same key is re-created.
 * Authorization: CustomFieldDefinitionPolicy.
 */
class CustomFieldController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomFieldDefinition::class);

        return Inertia::render('CustomFields/Index', [
            'fields' => CustomFieldDefinition::query()->orderBy('object')->orderBy('sort_order')->orderBy('id')->get()->map(fn (CustomFieldDefinition $d) => [
                'id' => $d->id,
                'object' => $d->object->value,
                'object_label' => $d->object->label(),
                'key' => $d->key,
                'label' => $d->label,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'options' => array_values($d->options ?? []),
                'is_required' => $d->is_required,
                'is_filterable' => $d->is_filterable,
                'sort_order' => $d->sort_order,
            ])->all(),
            'options' => [
                'objects' => array_map(fn (CustomFieldObject $o) => ['value' => $o->value, 'label' => $o->label()], CustomFieldObject::cases()),
                'types' => array_map(fn (CustomFieldType $t) => ['value' => $t->value, 'label' => $t->label(), 'has_options' => $t->hasOptions()], CustomFieldType::cases()),
            ],
            'can' => ['manage' => $request->user()->can('custom_field.manage')],
        ]);
    }

    public function store(CustomFieldDefinitionRequest $request, SaveCustomFieldDefinition $save): RedirectResponse
    {
        $definition = $save->handle(null, $request->validated());

        return $this->toast("Đã thêm trường {$definition->label}.");
    }

    public function update(CustomFieldDefinitionRequest $request, int $id, SaveCustomFieldDefinition $save): RedirectResponse
    {
        $definition = CustomFieldDefinition::query()->findOrFail($id);
        $save->handle($definition, $request->validated());

        return $this->toast("Đã cập nhật trường {$definition->label}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $definition = CustomFieldDefinition::query()->findOrFail($id);
        $this->authorize('delete', $definition);
        $definition->delete();

        return $this->toast("Đã xoá trường {$definition->label}.");
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/settings/custom-fields');
    }
}
