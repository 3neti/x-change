export type FeedbackChannel = 'email' | 'mobile' | 'webhook';

export type FeedbackDestinations = Record<FeedbackChannel, string>;

export type ClassifiedFeedbackDestination = {
    channel: FeedbackChannel;
    value: string;
};

export function emptyFeedbackDestinations(): FeedbackDestinations {
    return {
        email: '',
        mobile: '',
        webhook: '',
    };
}

export function normalizePhilippineFeedbackMobile(value: string): string {
    const normalized = value.trim().replace(/[\s().-]+/g, '');

    if (/^\+639\d{9}$/.test(normalized)) {
        return normalized;
    }

    if (/^639\d{9}$/.test(normalized)) {
        return `+${normalized}`;
    }

    if (/^09\d{9}$/.test(normalized)) {
        return `+63${normalized.slice(1)}`;
    }

    if (/^9\d{9}$/.test(normalized)) {
        return `+63${normalized}`;
    }

    return '';
}

export function isValidFeedbackEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

export function isValidFeedbackWebhook(value: string): boolean {
    try {
        const url = new URL(value.trim());

        return url.protocol === 'https:' || url.protocol === 'http:';
    } catch {
        return false;
    }
}

export function classifyFeedbackDestination(
    value: string,
): ClassifiedFeedbackDestination | null {
    const candidate = value.trim();

    if (candidate === '') {
        return null;
    }

    if (isValidFeedbackWebhook(candidate)) {
        return {
            channel: 'webhook',
            value: new URL(candidate).toString(),
        };
    }

    if (isValidFeedbackEmail(candidate)) {
        return {
            channel: 'email',
            value: candidate.toLowerCase(),
        };
    }

    const mobile = normalizePhilippineFeedbackMobile(candidate);

    if (mobile !== '') {
        return {
            channel: 'mobile',
            value: mobile,
        };
    }

    return null;
}

export function splitFeedbackDestinationInput(value: string): string[] {
    return value
        .split(/[\s,;]+/)
        .map((candidate) => candidate.trim())
        .filter((candidate) => candidate !== '');
}
