export type EnumOption = { value: string; label: string };

export type EnumName =
    | 'sex'
    | 'multiplicity'
    | 'delivery_mode'
    | 'respiratory_support'
    | 'patient_status'
    | 'zone'
    | 'stage'
    | 'rop_status'
    | 'plus_disease'
    | 'rop_type'
    | 'management_plan'
    | 'visit_kind'
    | 'treatment_type'
    | 'eye_side';

export type ReminderCounts = {
    today: number;
    upcoming: number;
    overdue: number;
    treatment_pending: number;
    injection_surveillance: number;
    without_appointment: number;
    review: number;
    attention: number;
};

export type PatientRow = {
    id: number;
    file_number: string | null;
    name: string;
    sex: string;
    dob: string | null;
    birth_weight_g: number | null;
    ga_weeks: number | null;
    ga_days: number | null;
    multiplicity: string | null;
    status: string;
    phone: string | null;
    referral_date: string | null;
    exams_count: number;
    any_rop: boolean | null;
    highest_stage: string | null;
    any_plus: boolean;
    type_one: boolean;
    had_injection: boolean;
    had_laser: boolean;
    treatment_pending: boolean;
    first_visit_date: string | null;
    last_visit_date: string | null;
    next_appointment_date: string | null;
    last_injection_date: string | null;
    days_until_appointment: number | null;
    days_since_injection: number | null;
    pma_days_today: number | null;
    age_days_today: number | null;
    open_review_items_count: number | null;
    deleted_at: string | null;
};

export type Patient = PatientRow & {
    delivery_mode: string | null;
    referring_doctor: string | null;
    nicu_days: number | null;
    respiratory_support: string | null;
    support_days: number | null;
    o2_days: number | null;
    cpap_days: number | null;
    systemic_illness: string | null;
    phone_alt: string | null;
    address: string | null;
    notes: string | null;
    source_notes: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type Eye = 'right' | 'left';

export type EyeFindings = {
    [
        K in
            | 'dilatation'
            | 'lens'
            | 'plus'
            | 'zone'
            | 'stage'
            | 'rop_type'
            | 'rop_status'
            | 'notes' as `${Eye}_${K}`
    ]: string | null;
} & {
    right_a_rop: boolean | null;
    left_a_rop: boolean | null;
};

export type Visit = EyeFindings & {
    id: number;
    patient_id: number;
    kind: string;
    visit_date: string | null;
    examiner: string | null;
    assessment: string | null;
    management_plan: string | null;
    management_notes: string | null;
    next_visit_date: string | null;
    fee: number | null;
    notes: string | null;
    source_reference: string | null;
    pma_days: number | null;
    age_days: number | null;
    right_suggested_type: string | null;
    left_suggested_type: string | null;
};

export type Treatment = {
    id: number;
    patient_id: number;
    visit_id: number | null;
    type: string;
    eye: string;
    performed_date: string | null;
    agent: string | null;
    performed_by: string | null;
    location: string | null;
    notes: string | null;
    source_reference: string | null;
    pma_days: number | null;
};

export type Attachment = {
    id: number;
    patient_id: number;
    visit_id: number | null;
    original_name: string;
    mime_type: string;
    size: number;
    caption: string | null;
    is_image: boolean;
    url: string;
    created_at: string | null;
};

export type ReviewItem = {
    id: number;
    patient_id: number;
    field: string | null;
    issue: string;
    source_reference: string | null;
    resolution: string | null;
    resolved_at: string | null;
};

export type Audit = {
    id: number;
    event: 'created' | 'updated' | 'deleted' | 'restored';
    auditable_type: string;
    auditable_id: number;
    patient_id: number | null;
    label: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    user: string | null;
    patient_name: string | null;
    created_at: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type ChartItem = { label: string; value: number };

export type ChartData = {
    title: string;
    unit: string;
    total: number;
    unknown: number;
    items: ChartItem[];
};

export type Clinic = {
    name: string | null;
    doctor: string | null;
    address: string | null;
    phone: string | null;
};
