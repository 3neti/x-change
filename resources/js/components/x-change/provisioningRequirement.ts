export interface ProvisioningFlowDescriptor {
    provider?: string | null;
    topology?: string | null;
    mode?: string | null;
    title?: string | null;
    description?: string | null;
    steps?: string[];
    fields?: string[];
    actions?: string[];
    metadata?: Record<string, unknown> | null;
}

export interface ProvisioningRequirement {
    purpose?: string | null;
    provider?: string | null;
    topology?: string | null;
    mode?: string | null;
    reason?: string | null;
    missing?: string[];
    readiness?: Record<string, unknown> | null;
    onboarding?: {
        reference?: string | null;
        links?: {
            status_url?: string | null;
            resume_url?: string | null;
        } | null;
        [key: string]: unknown;
    } | null;
    descriptor?: ProvisioningFlowDescriptor | null;
}

function stringOrNull(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim();

    return normalized === '' ? null : normalized;
}

function stringArray(value: unknown): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((item): item is string => typeof item === 'string')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
}

function recordOrNull(value: unknown): Record<string, unknown> | null {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? value as Record<string, unknown>
        : null;
}

export function normalizeProvisioningRequirement(value: unknown): ProvisioningRequirement | null {
    const requirement = recordOrNull(value);

    if (!requirement) {
        return null;
    }

    const descriptor = recordOrNull(requirement.descriptor);

    return {
        purpose: stringOrNull(requirement.purpose),
        provider: stringOrNull(requirement.provider),
        topology: stringOrNull(requirement.topology),
        mode: stringOrNull(requirement.mode),
        reason: stringOrNull(requirement.reason),
        missing: stringArray(requirement.missing),
        readiness: recordOrNull(requirement.readiness),
        onboarding: (() => {
            const onboarding = recordOrNull(requirement.onboarding);

            if (! onboarding) {
                return null;
            }

            const links = recordOrNull(onboarding.links);

            return {
                ...onboarding,
                reference: stringOrNull(onboarding.reference),
                links: links ? {
                    ...links,
                    status_url: stringOrNull(links.status_url),
                    resume_url: stringOrNull(links.resume_url),
                } : null,
            };
        })(),
        descriptor: descriptor ? {
            provider: stringOrNull(descriptor.provider),
            topology: stringOrNull(descriptor.topology),
            mode: stringOrNull(descriptor.mode),
            title: stringOrNull(descriptor.title),
            description: stringOrNull(descriptor.description),
            steps: stringArray(descriptor.steps),
            fields: stringArray(descriptor.fields),
            actions: stringArray(descriptor.actions),
            metadata: recordOrNull(descriptor.metadata),
        } : null,
    };
}

export function formatProvisioningLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .trim();
}
