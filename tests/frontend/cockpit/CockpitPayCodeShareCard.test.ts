import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitPayCodeShareCard from '../../../resources/js/cockpit/components/CockpitPayCodeShareCard.vue';

const baseProps = {
    code: 'PC-SHARE-001',
    claimUrl: 'https://example.test/x/claim/PC-SHARE-001',
    claimQr: 'data:image/png;base64,FAKE-QR-PAYLOAD',
};

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('CockpitPayCodeShareCard', () => {
    it('renders a large QR, the visual || CODE || treatment, and the full canonical URL in the prominent variant', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, variant: 'prominent' },
        });

        const qr = wrapper.get('[data-testid="cockpit-pay-code-share-qr"]');

        expect(qr.attributes('src')).toBe(baseProps.claimQr);
        expect(qr.attributes('alt')).toBe(
            'QR code to claim Pay Code PC-SHARE-001',
        );
        expect(wrapper.get('[data-testid="cockpit-pay-code-share-code"]').text()).toBe(
            '|| PC-SHARE-001 ||',
        );
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-url-link"]').text(),
        ).toBe(baseProps.claimUrl);
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-share-url-link"]')
                .attributes('href'),
        ).toBe(baseProps.claimUrl);
    });

    it('passes a safe title, headline text, and the canonical URL to navigator.share', async () => {
        const nativeShare = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', { share: nativeShare });

        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-native"]')
            .trigger('click');

        expect(nativeShare).toHaveBeenCalledWith({
            title: 'Pay Code PC-SHARE-001',
            text: 'Claim Pay Code || PC-SHARE-001 ||',
            url: baseProps.claimUrl,
        });
    });

    it('does not surface an error when the operator cancels the native share sheet', async () => {
        const abortError = new DOMException('Share cancelled', 'AbortError');
        const nativeShare = vi.fn().mockRejectedValue(abortError);

        vi.stubGlobal('navigator', { share: nativeShare });

        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-native"]')
            .trigger('click');
        await Promise.resolve();
        await Promise.resolve();

        expect(nativeShare).toHaveBeenCalledOnce();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-status"]').text(),
        ).toBe('');
    });

    it('copies only the canonical claim URL, never the composed share message', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: { writeText },
        });

        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-copy"]')
            .trigger('click');

        expect(writeText).toHaveBeenCalledWith(baseProps.claimUrl);
        expect(writeText).not.toHaveBeenCalledWith(
            expect.stringContaining('\n'),
        );
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-copy"]').text(),
        ).toContain('Copied');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-status"]').text(),
        ).toContain('No delivery was sent');
    });

    it('produces accessible feedback in a polite live region when copy fails', async () => {
        vi.stubGlobal('navigator', {});

        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-copy"]')
            .trigger('click');

        const status = wrapper.get(
            '[data-testid="cockpit-pay-code-share-status"]',
        );

        expect(status.attributes('role')).toBe('status');
        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.text()).toContain('Copy is unavailable');
    });

    it('encodes correct SMS, email, WhatsApp, and Facebook share URLs', () => {
        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        const sms = wrapper
            .get('[data-testid="cockpit-pay-code-share-sms"]')
            .attributes('href');
        const email = wrapper
            .get('[data-testid="cockpit-pay-code-share-email"]')
            .attributes('href');
        const whatsapp = wrapper
            .get('[data-testid="cockpit-pay-code-share-whatsapp"]')
            .attributes('href');
        const facebook = wrapper
            .get('[data-testid="cockpit-pay-code-share-facebook"]')
            .attributes('href');

        expect(sms).toBe(
            `sms:?body=${encodeURIComponent(
                `Claim Pay Code || PC-SHARE-001 ||\n${baseProps.claimUrl}`,
            )}`,
        );
        expect(email).toBe(
            `mailto:?subject=${encodeURIComponent('Pay Code PC-SHARE-001')}&body=${encodeURIComponent(
                `Claim Pay Code || PC-SHARE-001 ||\n${baseProps.claimUrl}`,
            )}`,
        );
        expect(whatsapp).toBe(
            `https://wa.me/?text=${encodeURIComponent(
                `Claim Pay Code || PC-SHARE-001 ||\n${baseProps.claimUrl}`,
            )}`,
        );
        expect(facebook).toBe(
            `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(baseProps.claimUrl)}`,
        );

        const whatsappLink = wrapper.get(
            '[data-testid="cockpit-pay-code-share-whatsapp"]',
        );
        const facebookLink = wrapper.get(
            '[data-testid="cockpit-pay-code-share-facebook"]',
        );

        expect(whatsappLink.attributes('target')).toBe('_blank');
        expect(whatsappLink.attributes('rel')).toBe('noopener noreferrer');
        expect(facebookLink.attributes('target')).toBe('_blank');
        expect(facebookLink.attributes('rel')).toBe('noopener noreferrer');
    });

    it('downloads the provided QR data URI with a deterministic sanitized filename', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, code: 'PC Wave 55 / Test!' },
        });

        const download = wrapper.get(
            '[data-testid="cockpit-pay-code-share-download"]',
        );

        expect(download.attributes('href')).toBe(baseProps.claimQr);
        expect(download.attributes('download')).toBe(
            'pay-code-pc-wave-55-test.png',
        );
    });

    it('hides the Download QR action when no QR data URI is available', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, claimQr: null },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-share-download"]')
                .exists(),
        ).toBe(false);
        // The rest of the card keeps working even without a QR.
        expect(
            wrapper.find('[data-testid="cockpit-pay-code-share-copy"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="cockpit-pay-code-share-code"]').exists(),
        ).toBe(true);
    });

    it('keeps the explicit action shortcuts available when native sharing is unavailable', () => {
        vi.stubGlobal('navigator', {});

        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });

        expect(
            wrapper.find('[data-testid="cockpit-pay-code-share-native"]').exists(),
        ).toBe(false);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-copy"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-sms"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-email"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-whatsapp"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-facebook"]').exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-download"]').exists(),
        ).toBe(true);
    });

    it('renders nothing when no canonical claim URL is available (non-claimable Pay Code)', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, claimUrl: null },
        });

        expect(
            wrapper.find('[data-testid="cockpit-pay-code-share-card"]').exists(),
        ).toBe(false);
        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('never places raw sensitive claim or instruction data into the share text', () => {
        const wrapper = mount(CockpitPayCodeShareCard, { props: baseProps });
        const sms = decodeURIComponent(
            wrapper
                .get('[data-testid="cockpit-pay-code-share-sms"]')
                .attributes('href') ?? '',
        );

        expect(sms).not.toContain('secret');
        expect(sms).not.toContain('mobile');
        expect(sms).not.toContain('provider');
        expect(sms).toContain('PC-SHARE-001');
        expect(sms).toContain(baseProps.claimUrl);
    });

    it('gives the prominent variant a min-w-0/wrapping-safe layout with no fixed widths', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, variant: 'prominent' },
        });
        const card = wrapper.get('[data-testid="cockpit-pay-code-share-card"]');

        expect(card.classes()).toContain('min-w-0');
        expect(card.classes()).toContain('grid');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-share-lead"]')
                .classes(),
        ).toContain('sm:grid-cols-[auto_minmax(0,1fr)]');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-share-code"]')
                .classes(),
        ).toContain('break-all');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-share-url"]').classes(),
        ).toContain('break-all');

        const html = card.html();

        expect(html.match(/\bw-\[/g)).toBeNull();
        expect(
            html
                .match(/min-w-(\d|\[)/g)
                ?.filter((match) => match !== 'min-w-0') ?? [],
        ).toHaveLength(0);
        expect(html).not.toContain('class="absolute');
    });

    it('renders the compact variant borderless without the large QR image, but keeps Download QR working', () => {
        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, variant: 'compact' },
        });
        const card = wrapper.get('[data-testid="cockpit-pay-code-share-card"]');

        expect(card.attributes('data-variant')).toBe('compact');
        expect(card.classes()).toContain('min-w-0');
        expect(card.classes()).not.toContain('border');
        expect(
            wrapper.find('[data-testid="cockpit-pay-code-share-qr"]').exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-share-download"]')
                .attributes('href'),
        ).toBe(baseProps.claimQr);
    });

    it('shows optional safe context text without embedding it into the share message', async () => {
        const nativeShare = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', { share: nativeShare });

        const wrapper = mount(CockpitPayCodeShareCard, {
            props: { ...baseProps, contextText: 'Claim this Pay Code · PHP 500.00' },
        });

        expect(wrapper.text()).toContain('Claim this Pay Code · PHP 500.00');

        await wrapper
            .get('[data-testid="cockpit-pay-code-share-native"]')
            .trigger('click');

        expect(nativeShare).toHaveBeenCalledWith(
            expect.objectContaining({
                text: expect.not.stringContaining('PHP 500.00'),
            }),
        );
    });
});
