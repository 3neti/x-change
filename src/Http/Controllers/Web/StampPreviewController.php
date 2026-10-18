<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;
use LBHurtado\XStamp\Renderer;

final class StampPreviewController
{
    public function __invoke(Request $request, StampCatalog $catalog, Renderer $renderer, StampProviderIdentity $providers): JsonResponse
    {
        $input = $request->validate([
            'design_id' => ['required', 'string', 'max:80'],
            'revision' => ['required', 'integer', 'min:1'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'position' => ['sometimes', 'in:top_left,top_right,bottom_left,bottom_right'],
            'slots' => ['required', 'array:brand,tagline,amount,recipient,purpose,expiry,instructions'],
            'slots.*' => ['nullable', 'string', 'max:1024'],
        ]);
        try {
            $design = $catalog->presentation($input['design_id'], (int) $input['revision'], $input['position'] ?? 'bottom_right', $input['slots']['amount'] ?? '');
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $slots = array_map(fn (?string $value): string => $value ?? '', $input['slots']);

        $identity = $providers->resolve($input['provider'] ?? null);
        $images = ($slots['brand'] ?? '') !== '' && isset($identity['logo']) ? ['provider_logo' => $identity['logo']] : [];
        $slots['provider_name'] = ($slots['brand'] ?? '') !== '' && $images === [] ? ($identity['name'] ?? '') : '';
        $design = $catalog->providerPresentation($design, $slots['provider_name'], $images !== []);

        return response()->json(['provider_identity' => $identity === null ? null : ['code' => $identity['code'], 'name' => $identity['name']], 'image' => 'data:image/png;base64,'.base64_encode($renderer->png($design, $slots, $images)), 'marker_palette' => $catalog->markerPalette($design)])->header('Cache-Control', 'no-store');
    }
}
