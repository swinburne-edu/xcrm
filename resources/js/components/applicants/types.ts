import type { CustomField } from '@/components/custom/types';
import type { FilterDefinition } from '@/types';

export type Option = { value: string; label: string };

export type ApplicantOptions = {
    genders: Option[];
    relationships: Option[];
    scoreTypes: Option[];
    certificateTypes: Option[];
    terms: string[];
    sources: Option[];
    priorityGroups: Option[];
    priorityAreas: Option[];
    customFields: CustomField[];
    customFilters: FilterDefinition[];
};

export type ApplicantRow = {
    custom_data?: Record<string, unknown> | null;
    id: string;
    uuid: string;
    code: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    province_name: string | null;
    high_school_name: string | null;
    high_school_id: number | null;
    class_name: string | null;
    current_grade: number | null;
    graduation_year: number | null;
    created_at: string | null;
    // detail only
    gender?: string | null;
    gender_label?: string | null;
    dob?: string | null;
    citizen_id?: string | null;
    sensitive_masked?: boolean;
    zalo_id?: string | null;
    facebook_id?: string | null;
    province_id?: number | null;
    ward_id?: number | null;
    ward_name?: string | null;
    address?: string | null;
    priority_area_id?: number | null;
    priority_area_code?: string | null;
    priority_group_id?: number | null;
    priority_group_code?: string | null;
    first_source_id?: number | null;
    first_source_name?: string | null;
    first_campaign_id?: number | null;
    first_campaign_name?: string | null;
    referred_by_user_id?: number | null;
    referrer_name?: string | null;
    consent_marketing?: boolean;
};

export type GuardianRow = {
    id: number;
    full_name: string;
    phone: string | null;
    email: string | null;
    zalo_id: string | null;
    occupation: string | null;
    note: string | null;
    relationship: string | null;
    relationship_label: string | null;
    is_decision_maker: boolean;
    is_primary_contact: boolean;
};

export type ScoreRow = {
    score_type: string;
    subject_id: number | null;
    term: string | null;
    exam_name: string | null;
    score: number | null;
    max_score: number;
    year: number | null;
    verified: boolean;
};

export type CertificateRow = {
    id: number;
    type: string;
    type_label: string;
    score: string | null;
    issued_at: string | null;
    expires_at: string | null;
    verified: boolean;
};

export type SubjectRow = { id: number; code: string; name: string; is_core: boolean };

export type DuplicateMatch = {
    field: string;
    label: string;
    visible: boolean;
    uuid: string | null;
    code: string | null;
    full_name: string | null;
};

export const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
export const textareaClass =
    'w-full rounded-md border border-input bg-transparent p-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
