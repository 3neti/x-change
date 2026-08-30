import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const pageFiles = [
    'QuickGenerate.vue',
    'Funding.vue',
    'Dashboard.vue',
    'PayCodeExplorer.vue',
    'Campaigns.vue',
] as const;

function pageSource(file: (typeof pageFiles)[number]): string {
    return readFileSync(
        resolve(import.meta.dirname, `../../../resources/js/cockpit/pages/${file}`),
        'utf8',
    );
}

describe('Cockpit mobile edge canvas', () => {
    it.each(pageFiles)('opts %s into the edge canvas with contained secondary sections', (file) => {
        const source = pageSource(file);

        expect(source).toContain('mobile-presentation="edge"');
        expect(source).toContain('px-4 md:px-0');
    });

    it('makes each primary operating surface full bleed only below md', () => {
        const primarySources = [
            pageSource('Funding.vue'),
            pageSource('Dashboard.vue'),
            pageSource('PayCodeExplorer.vue'),
            pageSource('Campaigns.vue'),
            readFileSync(
                resolve(
                    import.meta.dirname,
                    '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue',
                ),
                'utf8',
            ),
            readFileSync(
                resolve(
                    import.meta.dirname,
                    '../../../resources/js/cockpit/components/CockpitQuickGeneratePosPanel.vue',
                ),
                'utf8',
            ),
        ];

        for (const source of primarySources) {
            expect(source).toContain('-mx-4');
            expect(source).toContain('border-y');
            expect(source).toContain('shadow-none');
            expect(source).toMatch(/md:mx-(?:0|auto)/);
            expect(source).toMatch(/md:rounded-(?:2xl|3xl)/);
            expect(source).toContain('md:border');
            expect(source).toContain('md:shadow-sm');
        }
    });
});
