export type Option = { value: string; label: string };

export type TimelineEntry = {
    id: number;
    type: string;
    type_label: string;
    direction: 'in' | 'out' | null;
    title: string | null;
    body: string | null;
    result: string | null;
    duration_sec: number | null;
    occurred_at: string;
    user_name: string | null;
    on_case: boolean;
    meta: Record<string, unknown> | null;
};

export type TaskRow = {
    id: number;
    title: string;
    type: string;
    type_label: string;
    priority: string;
    priority_label: string;
    status: 'todo' | 'done' | 'cancelled';
    status_label: string;
    due_at: string | null;
    due_input: string | null;
    overdue: boolean;
    completed_at: string | null;
    assignee_id: number | null;
    assignee_name: string | null;
    subject_label: string | null;
    subject_href: string | null;
};

export type AppointmentRow = {
    id: number;
    case_id: number;
    applicant_name: string | null;
    applicant_href: string | null;
    type: string;
    type_label: string;
    start_at: string;
    start_input: string | null;
    end_input: string | null;
    location: string | null;
    host_id: number | null;
    host_name: string | null;
    attendance: 'scheduled' | 'attended' | 'no_show' | 'cancelled';
    attendance_label: string;
    result: string | null;
};

export type TimelineSubjectType = 'applicant' | 'case' | 'high_school' | 'campaign';

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
export const textareaClass =
    'w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
