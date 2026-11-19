/** Custom fields (task 2.5): a field with the current value, the block shown on a detail page, and the settings row. */
export type CustomFieldType = 'text' | 'textarea' | 'number' | 'date' | 'boolean' | 'select' | 'multiselect' | 'url' | 'phone' | 'email';

export type CustomFieldValue = string | number | boolean | string[] | null;

export type CustomField = {
    key: string;
    label: string;
    type: CustomFieldType;
    options: string[];
    required: boolean;
    value: CustomFieldValue;
};

export type CustomPanel = {
    object: string;
    id: number;
    fields: CustomField[];
};

export type CustomFieldDefinitionRow = {
    id: number;
    object: string;
    object_label: string;
    key: string;
    label: string;
    type: CustomFieldType;
    type_label: string;
    options: string[];
    is_required: boolean;
    is_filterable: boolean;
    sort_order: number;
};

/** Values by key for a `custom` block; blank stays '' / [] so the server can clear it. */
export type CustomValues = Record<string, CustomFieldValue>;

/** Starting values of a form: the record's saved data (when given) over each field's own value, blanks by type. */
export function blankValues(fields: CustomField[], data?: Record<string, unknown> | null): CustomValues {
    return Object.fromEntries(
        fields.map((f) => {
            const v = (data?.[f.key] ?? f.value) as CustomFieldValue | undefined;

            return [f.key, v ?? (f.type === 'multiselect' ? [] : f.type === 'boolean' ? false : '')];
        }),
    );
}

export function displayValue(field: CustomField): string {
    const v = field.value;

    if (v === null || v === '' || (Array.isArray(v) && v.length === 0)) {
        return '—';
    }

    if (field.type === 'boolean') {
        return v ? 'Có' : 'Không';
    }

    if (field.type === 'multiselect' && Array.isArray(v)) {
        return v.join(', ');
    }

    if (field.type === 'date' && typeof v === 'string') {
        const [y, m, d] = v.split('-');

        return d && m && y ? `${d}/${m}/${y}` : v;
    }

    if (field.type === 'number' && typeof v === 'number') {
        return v.toLocaleString('vi-VN');
    }

    return String(v);
}
