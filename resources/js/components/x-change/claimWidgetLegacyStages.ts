import type { RawRiderStage } from '@/components/x-rider/types';

export function extractStages(value: unknown): RawRiderStage[] {
    if (Array.isArray(value)) {
        return value as RawRiderStage[];
    }

    if (
        value
        && typeof value === 'object'
        && Array.isArray((value as { stages?: unknown }).stages)
    ) {
        return (value as { stages: RawRiderStage[] }).stages;
    }

    return [];
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value && typeof value === 'object' && !Array.isArray(value));
}

export function instructionSplashStage(data: Record<string, any>): RawRiderStage | null {
    const rider = data.instructions?.rider;

    if (!isRecord(rider) || typeof rider.splash !== 'string' || rider.splash.trim() === '') {
        return null;
    }

    const splashMeta = isRecord(rider.splash_meta)
        ? rider.splash_meta
        : {};

    return {
        type: 'splash',
        key: 'legacy-splash',
        enabled: true,
        phase: 'pre_claim',
        presentation: 'fullscreen',
        content: rider.splash,
        content_type: 'html',
        payload: {
            content: rider.splash,
            content_type: 'html',
            timeout: rider.splash_timeout ?? null,
            presentation: 'fullscreen',
            meta: splashMeta,
        },
        meta: splashMeta,
    };
}

export function hydrateInstructionSplashMeta(
    stage: RawRiderStage,
    data: Record<string, any>,
): RawRiderStage {
    if (stage.key !== 'legacy-splash') {
        return stage;
    }

    const rider = data.instructions?.rider;
    const splashMeta = isRecord(rider?.splash_meta)
        ? rider.splash_meta
        : {};

    return {
        ...stage,
        presentation: stage.presentation ?? 'fullscreen',
        content_type: stage.content_type ?? stage.payload?.content_type ?? 'html',
        payload: {
            ...(stage.payload ?? {}),
            content_type: stage.payload?.content_type ?? stage.content_type ?? 'html',
            presentation: stage.payload?.presentation ?? stage.presentation ?? 'fullscreen',
            meta: {
                ...(stage.payload?.meta ?? {}),
                ...splashMeta,
            },
        },
        meta: {
            ...(stage.meta ?? {}),
            ...splashMeta,
        },
    };
}

export function mergeStageWithRaw(
    stage: RawRiderStage,
    rawStages: RawRiderStage[],
): RawRiderStage {
    if (!stage.key) {
        return stage;
    }

    const raw = rawStages.find((candidate) => candidate.key === stage.key);

    if (!raw) {
        return stage;
    }

    return {
        ...raw,
        ...stage,
        payload: {
            ...(raw.payload ?? {}),
            ...(stage.payload ?? {}),
            meta: {
                ...(raw.payload?.meta ?? {}),
                ...(stage.payload?.meta ?? {}),
            },
        },
        meta: {
            ...(raw.meta ?? {}),
            ...(stage.meta ?? {}),
        },
        phase: stage.phase ?? raw.phase,
        presentation: stage.presentation ?? raw.presentation,
        content: stage.content ?? raw.content,
        content_type: stage.content_type ?? raw.content_type,
    };
}

export function uniqueStages(stages: RawRiderStage[]): RawRiderStage[] {
    const seen = new Set<string>();

    return stages.filter((stage, index) => {
        const key = stage.key ?? `${stage.type}-${index}`;

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

export function resolveLegacyRiderStages(
    data: Record<string, any> | null | undefined,
): RawRiderStage[] {
    if (!data) {
        return [];
    }

    const resolved = extractStages(data.rider?.stages);
    const raw = extractStages(data.instructions?.rider?.stages);
    const instructionSplash = instructionSplashStage(data);

    const mergedResolved = resolved.map((stage) =>
        hydrateInstructionSplashMeta(
            mergeStageWithRaw(stage, raw),
            data,
        ),
    );

    const missingRaw = raw.filter((rawStage, index) => {
        const rawKey = rawStage.key ?? `${rawStage.type}-${index}`;

        return !mergedResolved.some((stage, stageIndex) => {
            const key = stage.key ?? `${stage.type}-${stageIndex}`;

            return key === rawKey;
        });
    });

    const stages = uniqueStages([
        ...mergedResolved,
        ...missingRaw.map((stage) => hydrateInstructionSplashMeta(stage, data)),
        ...(instructionSplash ? [instructionSplash] : []),
    ]);

    return stages.map((stage) =>
        hydrateInstructionSplashMeta(stage, data),
    );
}
