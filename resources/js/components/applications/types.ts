/** Shapes shared by the nguyện vọng and document screens (task 2.2). Mirrors App\Domain\Admission\Services\ApplicationPanel. */
export type ApplicationAction = { action: string; label: string };

export type RequiredDocument = { type: string; label: string; approved: boolean };

export type ScoreBreakdown = {
    source?: string;
    scale_to?: number;
    combination?: string;
    subjects?: { subject: string; weight: number; score: number | null; from_certificate: boolean }[];
    exam?: { name: string | null; score: number; max: number };
    priority?: { area: number; group: number; base: number; applied: number; tapered: boolean };
    min_total?: number | null;
    meets_minimum?: boolean;
    missing?: string[];
    note?: string;
};

export type ApplicationItem = {
    id: number;
    case_uuid: string | null;
    preference_order: number;
    round_id: number;
    round: string | null;
    major_id: number;
    major: string | null;
    cycle_method_id: number;
    method: string | null;
    combination_id: number | null;
    combination: string | null;
    raw_score: number | null;
    priority_bonus: number | null;
    encourage_bonus: number | null;
    final_score: number | null;
    missing: string[];
    meets_minimum: boolean | null;
    breakdown: ScoreBreakdown;
    status: string;
    status_label: string;
    status_tone: 'gray' | 'blue' | 'violet' | 'green' | 'red' | 'amber';
    submitted_at: string | null;
    required_documents: RequiredDocument[];
    actions: ApplicationAction[];
    can: { update: boolean; delete: boolean; score: boolean };
};

export type ApplicationMajorOption = {
    id: number;
    name: string;
    methods: { id: number; label: string | null; direct: boolean; needs_combination: boolean }[];
    combinations: { id: number; code: string }[];
};

export type ApplicationOptions = { majors: ApplicationMajorOption[]; rounds: { id: number; name: string }[] };

export type ApplicationPanelProps = {
    cycle: string | null;
    case_uuid: string | null;
    closed: boolean;
    items: ApplicationItem[];
    options: ApplicationOptions | null;
    can_create: boolean;
};

export type DocumentItem = {
    id: number;
    doc_type: string;
    type_label: string;
    status: 'pending' | 'approved' | 'rejected';
    status_label: string;
    status_tone: 'amber' | 'green' | 'red';
    note: string | null;
    file_name: string | null;
    size: number | null;
    mime: string | null;
    uploaded_at: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    can_delete: boolean;
};

export type DocumentPanelProps = {
    items: DocumentItem[];
    types: { value: string; label: string }[];
    can: { upload: boolean; review: boolean };
};

export const SOURCE_LABELS: Record<string, string> = {
    thpt_exam: 'Điểm thi tốt nghiệp THPT',
    transcript: 'Học bạ THPT',
    competency_exam: 'Đánh giá năng lực',
    thinking_exam: 'Đánh giá tư duy',
    talent: 'Năng khiếu',
    direct: 'Xét tuyển thẳng',
};

/** Actions that need a confirmation before they are sent. */
export const CONFIRM_ACTIONS = new Set(['withdraw', 'reject', 'ineligible']);
