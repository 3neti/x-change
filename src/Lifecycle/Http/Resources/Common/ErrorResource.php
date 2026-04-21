<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Common;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $error = is_array($this->resource) ? $this->resource : [];

        return [
            'error' => [
                'code' => $error['code'] ?? 'UNSPECIFIED_ERROR',
                'message' => $error['message'] ?? 'An error occurred.',
                'details' => $error['details'] ?? new \stdClass(),
                'correlation_id' => $error['correlation_id'] ?? null,
            ],
        ];
    }
}
