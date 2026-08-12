import type { Component } from 'vue';
import {
    AlertTriangle,
    Ban,
    Camera,
    CalendarClock,
    CheckCircle2,
    Clock,
    HelpCircle,
    IdCard,
    KeyRound,
    Lock,
    MapPin,
    PenLine,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
    User,
    Wallet,
    XCircle,
} from 'lucide-vue-next';

export type ClaimSurfaceBadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

/**
 * Icon + badge tone per normalized state key. Presentation only -- the
 * state key/label themselves come straight from the backend contract (see
 * `DefaultVoucherOperationalStatusResolver` / `ClaimSurfaceStateData`).
 */
const STATUS_ICONS: Record<string, Component> = {
    active: CheckCircle2,
    partially_claimed: CheckCircle2,
    redeemed: CheckCircle2,
    paid: CheckCircle2,
    payout_pending: Clock,
    awaiting_approval: Clock,
    scheduled: CalendarClock,
    locked: Lock,
    expired: Clock,
    cancelled: Ban,
    closed: Ban,
    payout_rejected: AlertTriangle,
};

const STATUS_BADGE_VARIANTS: Record<string, ClaimSurfaceBadgeVariant> = {
    active: 'default',
    partially_claimed: 'default',
    paid: 'default',
    redeemed: 'secondary',
    scheduled: 'secondary',
    locked: 'secondary',
    payout_pending: 'secondary',
    awaiting_approval: 'secondary',
    expired: 'destructive',
    cancelled: 'destructive',
    closed: 'destructive',
    payout_rejected: 'destructive',
};

export function statusIcon(statusKey: string | null | undefined): Component {
    return STATUS_ICONS[String(statusKey ?? '')] ?? HelpCircle;
}

export function statusBadgeVariant(statusKey: string | null | undefined): ClaimSurfaceBadgeVariant {
    return STATUS_BADGE_VARIANTS[String(statusKey ?? '')] ?? 'secondary';
}

/**
 * Requirement row icon, keyed by `claim_requirement_summary` item keys.
 * Overlaps intentionally with `xrayClaimPreviewViewModel.ts`'s requirement
 * icon vocabulary where the keys are the same.
 */
const REQUIREMENT_ICONS: Record<string, Component> = {
    mobile: Smartphone,
    destination_account: Wallet,
    name: User,
    selfie: Camera,
    location: MapPin,
    signature: PenLine,
    kyc: IdCard,
    secret: KeyRound,
    otp: ShieldCheck,
    approval: ShieldAlert,
};

export function requirementIcon(key: string | null | undefined): Component | null {
    return REQUIREMENT_ICONS[String(key ?? '')] ?? null;
}

export type ClaimRequirementTone = 'positive' | 'warning' | 'critical' | 'neutral';

const TONE_BADGE_VARIANTS: Record<ClaimRequirementTone, ClaimSurfaceBadgeVariant> = {
    positive: 'default',
    warning: 'secondary',
    critical: 'destructive',
    neutral: 'outline',
};

export function toneBadgeVariant(tone: string | null | undefined): ClaimSurfaceBadgeVariant {
    return TONE_BADGE_VARIANTS[(tone as ClaimRequirementTone) ?? 'neutral'] ?? 'outline';
}

/**
 * Human-readable status label for a requirement row's `status` value
 * (already a safe, non-raw string from the backend -- this only
 * capitalizes it for display).
 */
export function humanizeRequirementStatus(status: string | null | undefined): string {
    const value = String(status ?? '').trim();

    if (value === '') {
        return 'Unknown';
    }

    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}
