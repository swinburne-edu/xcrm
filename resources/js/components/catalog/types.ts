import type { FilterDefinition, FilterOption } from '@/types';

/** Shapes sent by App\Domain\Catalog\Catalogs\CatalogDefinition::schema(). */

export type CatalogColumn = {
    key: string;
    label: string;
    type?: 'text' | 'code' | 'number' | 'boolean' | 'badge';
    sortable?: boolean;
    trueLabel?: string;
    falseLabel?: string;
};

export type CatalogField = {
    key: string;
    label: string;
    type:
        | 'text'
        | 'number'
        | 'select'
        | 'boolean'
        | 'entity'
        | 'combination_subjects';
    required?: boolean;
    options?: FilterOption[];
    entity?: string;
    /** Row key holding the label of an entity value (for edit forms). */
    labelKey?: string;
    placeholder?: string;
    help?: string;
    maxlength?: number;
    step?: number;
    default?: unknown;
};

export type CatalogSchema = {
    key: string;
    title: string;
    description: string | null;
    columns: CatalogColumn[];
    filters: FilterDefinition[];
    fields: CatalogField[];
};

export type CatalogRow = { id: number; [key: string]: unknown };

export type CombinationSubjectRow = {
    subject_id: number | string | null;
    weight: number | string;
    name?: string;
    code?: string;
};
