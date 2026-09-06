export type Option = { value: string; label: string };
export type Program = { id: number; code: string; name: string };
export type Campus = { id: number; code: string; name: string; programs: Program[] };

export type StudentFormData = {
    id?: number;
    name: string;
    email: string;
    enrollment_number: string;
    campus_id: number | '';
    academic_program_id: number | '';
    current_semester: number | '';
    group_name: string;
    academic_status: string;
    status_reason?: string;
    personal_email: string;
    phone: string;
    preferred_contact_channel: string;
    locale: string;
    photo_url?: string | null;
    activation_pending?: boolean;
    status_history?: StatusHistory[];
};

export type StatusHistory = {
    id: number;
    from: string | null;
    to: string;
    reason: string | null;
    changed_by: string;
    changed_at: string;
};

export type PageLink = { url: string | null; label: string; active: boolean };

