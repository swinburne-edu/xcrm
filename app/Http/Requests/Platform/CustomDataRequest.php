<?php

namespace App\Http\Requests\Platform;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Services\CustomFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /custom-data/{object}/{id}: inline edit of the custom fields of a record. The record must be visible to
 * the user (data scope, organization) and updatable under its own policy; the rules come from the definitions.
 */
class CustomDataRequest extends FormRequest
{
    private ?Model $record = null;

    public function object(): CustomFieldObject
    {
        return CustomFieldObject::tryFrom((string) $this->route('object')) ?? abort(404);
    }

    public function record(): Model
    {
        return $this->record ??= $this->object()->modelClass()::query()->findOrFail($this->route('id'));
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->record());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(CustomFields::class)->rules($this->object());
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return app(CustomFields::class)->attributes($this->object());
    }
}
