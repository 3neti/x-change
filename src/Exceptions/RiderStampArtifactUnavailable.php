<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class RiderStampArtifactUnavailable extends RuntimeException implements ShouldntReport
{
    public const string Message = 'The Pay Code presentation could not be finalized. No incomplete share image was published.';

    public function __construct()
    {
        parent::__construct(self::Message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => self::Message,
        ], 503);
    }
}
