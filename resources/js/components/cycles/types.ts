export type Option = { value: string; label: string };

export type CycleRow = {
    id: number;
    name: string;
    year: number;
    level: string;
    level_label: string;
    status: string;
    status_label: string;
    is_current: boolean;
    start_date: string | null;
    end_date: string | null;
    target_enrolled: number | null;
    regulation_ref: string | null;
    rounds_count: number | null;
    methods_count: number | null;
};

export type RoundRow = {
    id: number;
    name: string;
    type: string | null;
    type_label: string | null;
    apply_start: string | null;
    apply_end: string | null;
    result_date: string | null;
    confirm_deadline: string | null;
    sort_order: number;
};

/** Mirrors the score_formula JSON of docs/04-database.md §6. */
export type ScoreFormula = {
    source?: string;
    use_combination?: boolean;
    terms?: string[];
    aggregate?: string;
    subject_scale?: number;
    scale_to?: number;
    certificate_conversion?: unknown;
    priority?: { apply?: boolean; taper_from?: number };
    encourage_bonus_max?: number;
    min_total?: number;
};

export type MethodRow = {
    id: number;
    method_id: number;
    method_code: string | null;
    method_name: string | null;
    display_name: string | null;
    is_direct: boolean;
    score_formula: ScoreFormula | null;
    conditions: Record<string, unknown> | null;
    required_documents: string[];
    formula_summary: string | null;
};

export type FormulaOptions = {
    sources: Option[];
    aggregates: Option[];
    terms: string[];
    documents: Option[];
    maxMethods: number;
};

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50';
