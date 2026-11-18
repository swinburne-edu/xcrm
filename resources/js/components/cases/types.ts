export type Option = { value: string; label: string };

export type CaseCardData = {
    id: string;
    uuid: string;
    applicant_name: string | null;
    applicant_code: string | null;
    high_school_name: string | null;
    province_name: string | null;
    major_name: string | null;
    stage_id: number;
    stage_name: string | null;
    stage_color: string | null;
    status: string;
    owner_id: number | null;
    owner_name: string | null;
    in_pool: boolean;
    temperature: string | null;
    score: number;
    next_action_at: string | null;
    overdue: boolean;
    last_activity_at: string | null;
    tags: { id: number; name: string }[];
};

export type CaseDetail = CaseCardData & {
    applicant_uuid: string | null;
    phone: string | null;
    email: string | null;
    primary_major_id: number | null;
    source_id: number | null;
    source_name: string | null;
    campaign_id: number | null;
    campaign_name: string | null;
    team_name: string | null;
    next_action_input: string | null;
    assigned_at: string | null;
    first_contacted_at: string | null;
    lost_reason: string | null;
    lost_note: string | null;
    enrolled_at: string | null;
    cycle_closed: boolean;
    stages: { id: number; name: string; color: string | null; type: string; current: boolean }[];
    history: { from: string | null; to: string | null; by: string | null; at: string; hours: number | null }[];
    can: { update: boolean; assign: boolean; claim: boolean };
};

export type BoardColumn = {
    id: number;
    name: string;
    color: string | null;
    type: string;
    total: number;
    cards: CaseCardData[];
};

export type CaseOptions = {
    sources: Option[];
    temperatures: Option[];
    statuses: Option[];
    tags: Option[];
    lostReasons: Option[];
    stages: (Option & { type: string })[];
};

export type BulkResult = {
    done: number;
    failed: { uuid: string; name: string; message: string }[];
};

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

export function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('vi-VN') : '—';
}
