import { describe, expect, it } from 'vitest';
import { presentStampQr, styleStampQrPixels } from '../../../resources/js/cockpit/stampQrPresentation';

describe('Stamp QR presentation', () => {
    it('uses 50% paper and 30% center backing with 90% pattern and logo ink', () => {
        const pixels = new Uint8ClampedArray(256 * 256 * 4).fill(255);
        const module = (20 * 256 + 20) * 4;
        pixels.set([0, 0, 0, 255], module);
        const logo = (128 * 256 + 128) * 4;
        pixels.set([0, 80, 180, 255], logo);

        styleStampQrPixels(pixels, 256, 256, '#F8EDD4');

        expect(Array.from(pixels.slice(0, 4))).toEqual([248, 237, 212, 128]);
        expect(pixels[(110 * 256 + 110) * 4 + 3]).toBe(77);
        expect(pixels[module + 3]).toBe(242);
        expect(pixels[logo + 3]).toBe(237);
        expect(pixels[logo + 2]).toBeGreaterThan(pixels[logo]);
    });

    it('does not fetch external URLs or oversized images for presentation', async () => {
        const source = 'https://example.test/qr.png';
        expect(await presentStampQr(source, '#FFFFFF')).toBe(source);
        const oversized = 'data:image/png;base64,' + 'A'.repeat(512_001);
        expect(await presentStampQr(oversized, '#FFFFFF')).toBe(oversized);
    });
});
