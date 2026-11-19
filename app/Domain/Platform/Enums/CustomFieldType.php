<?php

namespace App\Domain\Platform\Enums;

/** Data type of a custom field (docs/database.dbml custom_field_definitions.type). */
enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Url = 'url';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Văn bản ngắn',
            self::Textarea => 'Văn bản dài',
            self::Number => 'Số',
            self::Date => 'Ngày',
            self::Boolean => 'Có / Không',
            self::Select => 'Chọn một',
            self::Multiselect => 'Chọn nhiều',
            self::Url => 'Đường dẫn (URL)',
            self::Phone => 'Số điện thoại',
            self::Email => 'Email',
        };
    }

    /** Select types carry a list of options. */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::Multiselect;
    }
}
