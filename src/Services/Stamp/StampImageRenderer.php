<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use DOMDocument;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;
use LBHurtado\XStamp\BrowserRenderer;
use LBHurtado\XStamp\Design;
use LBHurtado\XStamp\Renderer;
use LBHurtado\XStamp\RenderingProfile;
use RuntimeException;

final class StampImageRenderer
{
    /** @param array<string, string> $slots @param array<string, string> $images */
    public function png(Voucher $voucher, Design $design, array $slots, array $images, bool $customArtwork): string
    {
        $profile = app(StampRenderingProfiles::class)->forVoucher($voucher);
        $renderer = new Renderer;
        if ($profile === RenderingProfile::Standard) {
            return $renderer->png($design, $slots, $images);
        }
        $html = $renderer->html($design, $slots, $images);
        if ($customArtwork) {
            $source = $voucher->instructions->rider->stamp?->artwork_source?->value ?? $voucher->instructions->rider->og_source;
            if ($source !== 'splash' || in_array($voucher->instructions->rider->splash_format?->value, ['plain', 'markdown'], true)) {
                throw new RuntimeException('Browser artwork currently requires an HTML rider splash.');
            }
            $safe = app(RiderHtmlSanitizer::class)->sanitizeSplash((string) $voucher->instructions->rider->splash);
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8"><div id="stamp-artwork-root">'.$safe.'</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $imagesInHtml = $document->getElementsByTagName('img');
            if ($imagesInHtml->length > 1) {
                throw new RuntimeException('Browser splash supports one snapshotted artwork image.');
            }
            foreach ($imagesInHtml as $image) {
                $data = app(RiderSplashArtworkSnapshotterContract::class)->dataUrl($voucher);
                if ($data === null) {
                    throw new RuntimeException('The browser splash image has no verified snapshot.');
                }
                $image->setAttribute('src', $data);
                $image->removeAttribute('srcset');
            }
            $root = $document->getElementById('stamp-artwork-root');
            $artwork = '';
            foreach ($root->childNodes as $node) {
                $artwork .= $document->saveHTML($node);
            }
            $copySource = app(RiderStampCopyResolverContract::class)->resolve($voucher)->source;
            if (in_array($copySource, ['automatic', 'splash'], true)) {
                $slots['purpose'] = '';
            }
            $html = $renderer->artworkHtml($design, $artwork, $slots, $images);
        }
        $browser = new BrowserRenderer(
            nodeBinary: config('x-change.experience.stamp_rendering.node_binary'),
            nodeModulePath: config('x-change.experience.stamp_rendering.node_module_path'),
            chromePath: config('x-change.experience.stamp_rendering.chrome_path'),
            timeout: (int) config('x-change.experience.stamp_rendering.timeout', 20),
        );

        return $browser->screenshot($html, $design->document['width'], $design->document['height'], $profile);
    }
}
