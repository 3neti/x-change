<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LBHurtado\XChange\Services\Claim\GdRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\QrArtifactFactory;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampPresentation;
use LBHurtado\XChange\Services\Stamp\StampPreviewComposer;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;
use LBHurtado\XStamp\Appearance;
use LBHurtado\XStamp\IdentifierPresentation;
use LBHurtado\XStamp\QrPresentation;
use LBHurtado\XStamp\Renderer;

final class StampPreviewController
{
    public function __invoke(Request $request, StampCatalog $catalog, Renderer $renderer, StampProviderIdentity $providers, StampPreviewComposer $previews): JsonResponse
    {
        if ($request->has('instructions')) {
            $validated = $request->validate(['instructions' => ['required', 'array']]);

            try {
                return response()->json($previews->preview($validated['instructions']))->header('Cache-Control', 'no-store');
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        $input = $request->validate([
            'design_id' => ['required', 'string', 'max:80'],
            'revision' => ['required', 'integer', 'min:1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'position' => ['sometimes', 'in:top_left,top_right,bottom_left,bottom_right'],
            'presentation' => ['sometimes', 'nullable', 'array'],
            'marker' => ['sometimes', 'in:none,code,qr,both'],
            'code' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'slots' => ['required', 'array:brand,tagline,amount,recipient,purpose,expiry,instructions'],
            'slots.*' => ['nullable', 'string', 'max:1024'],
            'indicator_keys' => ['sometimes', 'array', 'list', 'max:32'],
            'indicator_keys.*' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.]*$/'],
        ]);
        try {
            $design = $catalog->presentation($input['design_id'], (int) $input['revision'], $input['position'] ?? 'bottom_right', $input['slots']['amount'] ?? '');
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $appearance = app(StampPresentation::class)->appearance($design, $input['presentation'] ?? null);
        $design = (new Appearance)->apply($design, $appearance);
        $slots = array_map(fn (?string $value): string => $value ?? '', $input['slots']);

        if (! $appearance['options']['show_tagline']) {
            $slots['tagline'] = '';
        }
        $identity = $providers->resolve($input['provider'] ?? null);
        $images = ($slots['brand'] ?? '') !== '' && isset($identity['logo']) ? ['provider_logo' => $identity['logo']] : [];
        $slots['provider_name'] = ($slots['brand'] ?? '') !== '' && $images === [] ? ($identity['name'] ?? '') : '';
        $design = $catalog->providerPresentation($design, $slots['provider_name'], $images !== []);

        $marker = $input['marker'] ?? 'none';
        $isSample = ($input['code'] ?? '') === '';
        $qr = null;
        if (in_array($marker, ['qr', 'both'], true)) {
            $artifact = $isSample
                ? app(QrArtifactFactory::class)->claimEntry()
                : app(QrArtifactFactory::class)->payCode($input['code'], route('x-change.claim.show', ['code' => $input['code']]));
            $qr = base64_decode(explode(',', $artifact->image_data_uri, 2)[1], true);
        }
        $palette = $catalog->markerPalette($design);
        $header = (new IdentifierPresentation)->compose($design, $isSample ? 'AA317' : $input['code'], $isSample);
        $qrKind = in_array($marker, ['qr', 'both'], true) ? 'qr' : 'none';
        $composition = (new QrPresentation)->compose($header['design'], $qr, $input['code'] ?? 'Code after issuance', $qrKind, $input['position'] ?? 'bottom_right', $palette['paper'], $palette['trim'], $appearance['qr']);

        $badges = app(GdRiderStampClaimShareCardRenderer::class)->systemIndicators($composition['design'], $input['indicator_keys'] ?? [], $input['position'] ?? 'bottom_right', $marker, $slots['amount'] ?? '');

        return response()->json([
            'requirements' => ['keys' => $badges['keys'], 'bounds' => $badges['bounds']],
            'identifier' => ['label' => $header['label'], 'sample' => $header['sample'], 'bounds' => $header['bounds']],
            'provider_identity' => $identity === null ? null : ['code' => $identity['code'], 'name' => $identity['name']],
            'image' => 'data:image/png;base64,'.base64_encode($renderer->png($badges['design'], $slots, [...$images, ...$composition['images'], ...$header['images'], ...$badges['images']])),
            'marker_palette' => $palette,
            'marker_bounds' => $composition['bounds'],
        ])->header('Cache-Control', 'no-store');
    }
}
