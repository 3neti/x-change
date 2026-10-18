export function styleStampQrPixels(
    pixels: Uint8ClampedArray,
    width: number,
    height: number,
    paper: string,
): void {
    const tone = [1, 3, 5].map((offset) =>
        parseInt(paper.slice(offset, offset + 2), 16),
    );
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const offset = (y * width + x) * 4;
            const brightness =
                (pixels[offset] + pixels[offset + 1] + pixels[offset + 2]) /
                255;
            const ink = Math.min(1, Math.max(0, 299 - 100 * brightness)) * 0.9;
            // The canonical 256px claim QR contains a centered 44 × 43px mark.
            const center =
                Math.abs(x + 0.5 - width / 2) < (width * 22) / 256 &&
                Math.abs(y + 0.5 - height / 2) < (height * 21.5) / 256;
            const backing = center ? 0.3 : 0.5;
            const alpha = ink + backing * (1 - ink);
            for (let channel = 0; channel < 3; channel++) {
                pixels[offset + channel] = Math.round(
                    (pixels[offset + channel] * ink +
                        tone[channel] * backing * (1 - ink)) /
                        alpha,
                );
            }
            pixels[offset + 3] = Math.round(alpha * 255);
        }
    }
}

export function presentStampQr(source: string, paper: string): Promise<string> {
    if (
        !source.startsWith('data:image/png;base64,') ||
        source.length > 512_000
    ) {
        return Promise.resolve(source);
    }
    return new Promise((resolve) => {
        const image = new Image();
        image.onerror = () => resolve(source);
        image.onload = () => {
            if (
                !image.width ||
                !image.height ||
                image.width * image.height > 1_000_000
            ) {
                resolve(source);
                return;
            }
            try {
                const canvas = document.createElement('canvas');
                canvas.width = image.width;
                canvas.height = image.height;
                const context = canvas.getContext('2d');
                if (!context) {
                    resolve(source);
                    return;
                }
                context.drawImage(image, 0, 0);
                const pixels = context.getImageData(
                    0,
                    0,
                    image.width,
                    image.height,
                );
                styleStampQrPixels(
                    pixels.data,
                    image.width,
                    image.height,
                    paper,
                );
                context.putImageData(pixels, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            } catch {
                resolve(source);
            }
        };
        image.src = source;
    });
}

export type StampOptions = {
    preset: 'clear' | 'soft' | 'transparent';
    crop: 'top' | 'center' | 'bottom';
    darkening: number;
    show_tagline: boolean;
};
export type StampPresentation = {
    schema?: string;
    design_id?: string;
    revision?: number;
    options: Partial<StampOptions>;
    resolved?: Record<string, number>;
};
export type StampDesignOption = {
    id: string;
    revision: number;
    name: string;
    thumbnail: string;
    presentation?: StampPresentation | null;
    customization?: {
        controls: string[];
        defaults: Partial<StampOptions>;
    } | null;
};
export function defaultStampPresentation(
    design?: StampDesignOption,
): StampPresentation | null {
    if (design?.presentation)
        return JSON.parse(JSON.stringify(design.presentation));
    return design?.customization
        ? {
              options: {
                  preset: 'transparent',
                  crop: 'center',
                  darkening: 0,
                  show_tagline: true,
                  ...design.customization.defaults,
              },
          }
        : null;
}
export function changeStampPresentation(
    current: StampPresentation | null,
    changes: Partial<StampOptions>,
): StampPresentation {
    // An explicit edit resolves against current configuration; restored snapshots remain frozen until edited.
    return { options: { ...current?.options, ...changes } };
}
