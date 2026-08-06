import { describe, expect, it } from 'vitest';
import { renderFundingQrMerchantLabel } from '../../../resources/js/cockpit/fundingQrMerchantLabel';

describe('funding QR merchant labels', () => {
    it('renders the selected template exactly as the payer will see it', () => {
        expect(
            renderFundingQrMerchantLabel({
                name: 'Amelia Hurtado',
                city: 'QC',
                applicationName: 'x-change',
                template: '{name} - {city}',
                maximumLength: 25,
                uppercase: true,
            }),
        ).toEqual({
            candidate: 'AMELIA HURTADO - QC',
            label: 'AMELIA HURTADO - QC',
            characterCount: 19,
            maximumLength: 25,
            overflow: 0,
            fits: true,
            shortened: false,
        });
    });

    it('shows the provider-safe crop while reporting the full overflow', () => {
        expect(
            renderFundingQrMerchantLabel({
                name: '123456789012345678901234567890',
                city: 'Manila',
                applicationName: 'x-change',
                template: '{name} - {city}',
                maximumLength: 25,
                uppercase: true,
            }),
        ).toEqual({
            candidate: '123456789012345678901234567890 - MANILA',
            label: '1234567890123456789012345',
            characterCount: 39,
            maximumLength: 25,
            overflow: 14,
            fits: false,
            shortened: true,
        });
    });

    it('supports every server-approved compact label format', () => {
        const common = {
            name: 'Amelia Hurtado',
            city: 'Manila',
            applicationName: 'x-change',
            maximumLength: 25,
            uppercase: false,
        };

        expect(
            renderFundingQrMerchantLabel({
                ...common,
                template: '{name}',
            }).label,
        ).toBe('Amelia Hurtado');
        expect(
            renderFundingQrMerchantLabel({
                ...common,
                template: '{name} - {city}',
            }).label,
        ).toBe('Amelia Hurtado - Manila');
        expect(
            renderFundingQrMerchantLabel({
                ...common,
                template: '{app_name} - {name}',
            }).label,
        ).toBe('x-change - Amelia Hurtado');
    });
});
