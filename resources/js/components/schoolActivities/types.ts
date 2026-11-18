/** Shapes of the school-activity screens (task 2.1). Mirrors SchoolActivityController::row(). */
export type ActivityTone = 'gray' | 'blue' | 'violet' | 'green' | 'red' | 'amber';

export type ActivityRow = {
    id: number;
    title: string;
    type: string;
    type_label: string;
    type_tone: ActivityTone;
    status: string;
    status_label: string;
    status_tone: ActivityTone;
    school: string | null;
    location: string | null;
    start_at: string | null;
    end_at: string | null;
    expected_attendees: number | null;
    checkins: number;
    staff: string[];
};

export type ActivityDetail = ActivityRow & {
    cycle_id: number;
    high_school_id: number | null;
    campaign_id: number | null;
    campaign_name: string | null;
    start_input: string | null;
    end_input: string | null;
    actual_attendees: number | null;
    cost: number | null;
    result_note: string | null;
    checkin_url: string;
    staff_ids: number[];
    lead_id: number | null;
    staff_items: { id: number; label: string }[];
};

export type AttendeeRow = {
    id: number;
    uuid: string | null;
    code: string | null;
    full_name: string | null;
    phone: string | null;
    class_name: string | null;
    checked_in_at: string | null;
};

export type ActivityOptions = {
    types: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
};
