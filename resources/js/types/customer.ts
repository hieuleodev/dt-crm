import type { Option } from "@/types";

export interface CustomerOptions {
    companyTypes: Option[];
    industries: Option[];
    tiers: Option[];
    statuses: Option[];
    lifecycleStages: Option[];
    sourceChannels: Option[];
    procurementMethods: Option[];
    buyingRoles: Option[];
    influences: Option[];
    attitudes: Option[];
    contactChannels: Option[];
    certificates: Option[];
    hazardAreas: Option[];
}

export interface OwnerOption {
    id: number;
    name: string;
}

export interface CustomerRow {
    id: number;
    code: string;
    name: string;
    short_name: string | null;
    tax_code: string | null;
    company_type: string | null;
    industry: string | null;
    location: string | null;
    tier: string | null;
    lifecycle_stage: Option;
    status: Option;
    owner: string | null;
    contacts_count: number;
    data_completeness: number;
    tags: string[];
}

export interface CustomerFormData {
    id?: number;
    code: string | null;
    name: string;
    short_name: string | null;
    tax_code: string | null;
    company_type: string | null;
    parent_group: string | null;
    parent_country: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    address: string | null;
    province: string | null;
    industrial_zone: string | null;
    industry: string | null;
    industry_detail: string | null;
    employee_count: number | null;
    ppe_budget_estimate: number | null;
    current_suppliers: string | null;
    source_channel: string | null;
    referred_by: string | null;
    tier: string | null;
    lifecycle_stage: string;
    status: string;
    owner_id?: number | null;
    procurement_method: string | null;
    payment_terms: string | null;
    credit_limit: number | null;
    vendor_registered: boolean;
    vendor_registered_at: string | null;
    required_certificates: string[];
    marketing_consent: boolean;
    consent_channels: string[];
    notes: string | null;
    tags: string[];
}

export interface CustomerDetail extends CustomerFormData {
    id: number;
    owner: OwnerOption | null;
    creator: string | null;
    created_at: string | null;
    lifecycle_stage_at: string | null;
    consent_at: string | null;
    last_contacted_at: string | null;
    data_completeness: number;
    missing: string[];
}

export interface ContactItem {
    id: number;
    full_name: string;
    title: string | null;
    department: string | null;
    plant_id: number | null;
    plant: string | null;
    buying_role: string | null;
    influence: string | null;
    attitude: string | null;
    phone: string | null;
    email: string | null;
    zalo: string | null;
    preferred_channel: string | null;
    language: string | null;
    birthday: string | null;
    is_primary: boolean;
    left_at: string | null;
    marketing_opt_out: boolean;
    notes: string | null;
}

export interface PlantItem {
    id: number;
    name: string;
    address: string | null;
    province: string | null;
    industrial_zone: string | null;
    employee_count: number | null;
    hazard_areas: string[] | null;
    receiving_hours: string | null;
    delivery_notes: string | null;
}

export interface OwnerHistoryItem {
    id: number;
    from: string | null;
    to: string | null;
    by: string | null;
    reason: string | null;
    at: string | null;
}

export interface AuditItem {
    id: number;
    event: "created" | "updated" | "deleted" | string;
    subject: string;
    user: string;
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    at: string | null;
}

export interface DuplicateMatch {
    id: number;
    code: string;
    name: string;
    owner: string | null;
    reasons: string[];
}
