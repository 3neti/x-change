<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XStamp\Renderer;

final class StampPreviewController
{
    public function __invoke(Request $request, StampCatalog $catalog, Renderer $renderer): JsonResponse
    {
        $input = $request->validate([
            'design_id' => ['required', 'string', 'max:80'],
            'revision' => ['required', 'integer', 'min:1'],
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

        return response()->json(['image' => 'data:image/png;base64,'.base64_encode($renderer->png($design, $slots)), 'marker_palette' => $catalog->markerPalette($design)])->header('Cache-Control', 'no-store');
    }
}
