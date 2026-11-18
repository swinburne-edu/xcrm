export type Option = { value: string; label: string };

export type SchoolOptions = {
    types: Option[];
    tiers: Option[];
    partnership: Option[];
    priorityAreas: Option[];
    combinations: string[];
    contactRoles?: Option[];
};

export type SchoolRow = {
    id: number;
    code: string | null;
    name: string;
    type: string | null;
    type_label: string | null;
    province_id: number | null;
    province_name: string | null;
    tier: string | null;
    partnership_status: string;
    partnership_label: string;
    grade12_students: number | null;
    owner_id: number | null;
    owner_name: string | null;
    contacts_count?: number;
    // detail only
    ward_id?: number | null;
    ward_name?: string | null;
    address?: string | null;
    priority_area_id?: number | null;
    priority_area_code?: string | null;
    lat?: number | null;
    lng?: number | null;
    grade12_classes?: number | null;
    strong_combinations?: string[];
    mou_signed_at?: string | null;
    phone?: string | null;
    email?: string | null;
    website?: string | null;
    note?: string | null;
};

export type ContactRow = {
    id: number;
    full_name: string;
    role: string | null;
    role_label: string | null;
    homeroom_class: string | null;
    phone: string | null;
    email: string | null;
    zalo: string | null;
    relationship_level: number | null;
    is_primary: boolean;
    note: string | null;
};

export type ImportResult = {
    created: number;
    updated: number;
    skipped: number;
    errors: { row: number; message: string }[];
    dry_run: boolean;
};

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
export const textareaClass =
    'w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
