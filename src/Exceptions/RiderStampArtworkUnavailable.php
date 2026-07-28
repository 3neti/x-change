<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RiderStampArtworkUnavailable extends RuntimeException implements ShouldntReport
{
    public const string Message = 'Rider Splash artwork could not be prepared. Retry the artwork, choose another image, or use x-change artwork before issuing.';

    public function __construct()
    {
        parent::__construct(self::Message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => self::Message,
            'errors' => [
                'rider.stamp.artwork_source' => [
                    self::Message,
                ],
            ],
        ], 422);
    }
}
