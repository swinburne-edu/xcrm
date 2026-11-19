/** Shapes of the lead ads integrations page (task 2.7); the data comes from Settings\LeadIntegrationController. */
export type LeadEventRow = {
    id: number;
    status: 'received' | 'created' | 'merged' | 'failed' | 'rejected';
    status_label: string;
    external_id: string | null;
    error: string | null;
    applicant: { uuid: string; name: string } | null;
    at: string | null;
};

export type LeadIntegrationRow = {
    id: number;
    name: string;
    provider: 'facebook' | 'tiktok';
    provider_label: string;
    is_active: boolean;
    webhook_url: string;
    campaign_id: number | null;
    campaign_name: string | null;
    source_id: number | null;
    source_name: string | null;
    has_secret: boolean;
    has_access_token: boolean;
    verify_token: string | null;
    field_map: { name: string; key: string }[];
    last_received_at: string | null;
    counts: Record<LeadEventRow['status'], number>;
    events: LeadEventRow[];
};

export type LeadIntegrationOptions = {
    providers: { value: string; label: string }[];
    campaigns: { value: number; label: string }[];
    sources: { value: number; label: string }[];
    lead_keys: Record<string, string>;
};

/** Text of the secret field per platform. */
export const secretLabels: Record<string, { label: string; help: string }> = {
    facebook: { label: 'App Secret của ứng dụng Facebook', help: 'Dùng để kiểm tra chữ ký X-Hub-Signature-256 của mỗi lần Facebook gửi lead.' },
    tiktok: { label: 'Khoá ký webhook (signing secret)', help: 'Dùng để kiểm tra chữ ký TikTok-Signature của mỗi lần TikTok gửi lead.' },
};

export const statusTone: Record<LeadEventRow['status'], 'gray' | 'green' | 'blue' | 'red' | 'amber'> = {
    received: 'gray',
    created: 'green',
    merged: 'blue',
    failed: 'red',
    rejected: 'amber',
};
