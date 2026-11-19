<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Services\CustomFields;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores custom field values into a record's `custom_data` (already validated with CustomFields::rules()).
 * Keys that are not in the input stay as they are; a blank value removes its key.
 */
class SaveCustomData
{
    public function __construct(private readonly CustomFields $fields) {}

    /**
     * @param  array<string, mixed>  $input  the `custom` block of the request
     */
    public function handle(Model $record, CustomFieldObject $object, array $input): Model
    {
        $data = $record->custom_data ?? [];

        foreach ($this->fields->clean($object, $input) as $key => $value) {
            $value === null ? $data = array_diff_key($data, [$key => true]) : $data[$key] = $value;
        }

        $record->custom_data = $data === [] ? null : $data;
        $record->save();

        return $record;
    }
}
