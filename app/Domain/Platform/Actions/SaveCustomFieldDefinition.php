<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Platform\Models\CustomFieldDefinition;

/**
 * Creates or edits a custom field definition (validated by CustomFieldDefinitionRequest).
 * Object, key and type are fixed once the field exists: values are already stored under that key and type.
 * Options are kept as a clean list of distinct, trimmed strings, and only for the select types.
 */
class SaveCustomFieldDefinition
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?CustomFieldDefinition $definition, array $data): CustomFieldDefinition
    {
        $isNew = $definition === null;
        $definition ??= new CustomFieldDefinition;
        $type = $isNew ? CustomFieldType::from($data['type']) : $definition->type;

        if ($isNew) {
            $object = CustomFieldObject::from($data['object']);
            $definition->object = $object;
            $definition->key = $data['key'];
            $definition->type = $type;
            $definition->sort_order = filled($data['sort_order'] ?? null)
                ? (int) $data['sort_order']
                : ((int) CustomFieldDefinition::query()->where('object', $object->value)->max('sort_order')) + 1;
        } elseif (filled($data['sort_order'] ?? null)) {
            $definition->sort_order = (int) $data['sort_order'];
        }

        $definition->label = trim($data['label']);
        $definition->options = $type->hasOptions() ? $this->options($data['options'] ?? []) : null;
        $definition->is_required = (bool) ($data['is_required'] ?? false);
        $definition->is_filterable = (bool) ($data['is_filterable'] ?? true);
        $definition->save();

        return $definition;
    }

    /**
     * @param  array<int, mixed>  $options
     * @return list<string>
     */
    private function options(array $options): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($o) => is_scalar($o) ? trim((string) $o) : '', $options),
            fn (string $o) => $o !== '',
        )));
    }
}
