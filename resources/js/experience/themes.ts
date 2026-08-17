export type ExperienceThemeId = 'default' | 'amber' | 'steampunk';

export type RiderStampDesignReference = {
    id: 'x-change-default' | 'x-change-amber' | 'x-change-steampunk';
    version: 1;
};

export type ExperienceThemeDefinition = {
    id: ExperienceThemeId;
    version: 1;
    name: string;
    description: string;
    browserColor: string;
    preview: {
        background: string;
        accent: string;
        foreground: string;
    };
    stampDesign: RiderStampDesignReference;
};

export const experienceThemeRegistry: readonly ExperienceThemeDefinition[] = [
    {
        id: 'default',
        version: 1,
        name: 'Default',
        description: 'Clear, calm, and familiar',
        browserColor: '#0f172a',
        preview: {
            background: '#f8fafc',
            accent: '#f97316',
            foreground: '#0f172a',
        },
        stampDesign: { id: 'x-change-default', version: 1 },
    },
    {
        id: 'amber',
        version: 1,
        name: 'Amber',
        description: 'Sunlit gold with quiet warmth',
        browserColor: '#9a3412',
        preview: {
            background: '#fff7ed',
            accent: '#ea580c',
            foreground: '#431407',
        },
        stampDesign: { id: 'x-change-amber', version: 1 },
    },
    {
        id: 'steampunk',
        version: 1,
        name: 'Steampunk',
        description: 'Aged parchment, brass, and ink',
        browserColor: '#713f12',
        preview: {
            background: '#f5e6c8',
            accent: '#a16207',
            foreground: '#3f2a14',
        },
        stampDesign: { id: 'x-change-steampunk', version: 1 },
    },
] as const;

export const experienceProfile = {
    id: 'x-change-core',
    version: 1,
    defaultTheme: 'default' as ExperienceThemeId,
    branding: { id: 'x-change', version: 1 },
    dictionary: { id: 'x-change-core', version: 1 },
    copy: { id: 'x-change-core', version: 1 },
    allowedThemes: experienceThemeRegistry.map((theme) => theme.id),
} as const;

export function findExperienceTheme(
    value: string | null | undefined,
): ExperienceThemeDefinition | null {
    return experienceThemeRegistry.find((theme) => theme.id === value) ?? null;
}

export function experienceThemeForStampDesign(
    designId: string | null | undefined,
): ExperienceThemeDefinition | null {
    return (
        experienceThemeRegistry.find(
            (theme) => theme.stampDesign.id === designId,
        ) ?? null
    );
}
