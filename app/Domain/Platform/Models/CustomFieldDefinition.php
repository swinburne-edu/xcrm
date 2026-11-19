<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Database\Factories\CustomFieldDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One custom field of an object. `key` (snake_case) is the key inside the object's `custom_data` JSON.
 * Select / multiselect fields list their choices in `options` (plain strings: value = label).
 *
 * @property int $id
 * @property int $organization_id
 * @property CustomFieldObject $object
 * @property string $key
 * @property string $label
 * @property CustomFieldType $type
 * @property list<string>|null $options
 * @property bool $is_required
 * @property bool $is_filterable
 * @property int $sort_order
 */
#[Fillable(['organization_id', 'object', 'key', 'label', 'type', 'options', 'is_required', 'is_filterable', 'sort_order'])]
#[UseFactory(CustomFieldDefinitionFactory::class)]
class CustomFieldDefinition extends Model
{
    /** @use HasFactory<CustomFieldDefinitionFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory;

    protected $attributes = ['is_required' => false, 'is_filterable' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'object' => CustomFieldObject::class,
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
