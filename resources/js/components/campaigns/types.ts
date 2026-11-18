export type Option = { value: string; label: string };

export type CampaignOptions = {
    statuses: Option[];
    types: Option[];
    channels: Option[];
};

export type TargetItem = { id: number; label: string };

export type CampaignRow = {
    id: number;
    code: string;
    name: string;
    type: string | null;
    type_label: string | null;
    channel: string | null;
    channel_label: string | null;
    status: string;
    status_label: string;
    start_date: string | null;
    end_date: string | null;
    budget: number | null;
    actual_cost: number | null;
    owner_id: number | null;
    owner_name: string | null;
    parent_id: number | null;
    parent_name: string | null;
    // detail only
    cycle_id?: number;
    cycle_name?: string | null;
    target_leads?: number | null;
    target_applications?: number | null;
    target_enrolled?: number | null;
    landing_url?: string | null;
    description?: string | null;
    approved_by_name?: string | null;
    approved_at?: string | null;
    targets?: {
        majors: TargetItem[];
        provinces: TargetItem[];
        high_schools: TargetItem[];
    };
};

export type Kpi = {
    available: boolean;
    leads: number | null;
    applications: number | null;
    enrolled: number | null;
    cost_per_lead: number | null;
    cost_per_enrolled: number | null;
};

export type Transition = { action: string; label: string; to: string };

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
export const textareaClass =
    'w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function money(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : `${new Intl.NumberFormat('vi-VN').format(value)} ₫`;
}
