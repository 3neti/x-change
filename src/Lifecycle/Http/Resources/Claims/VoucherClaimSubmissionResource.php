<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Claims;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherClaimSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'claim_submission' => $this->resource,
            ],
            'meta' => new \stdClass(),
        ];
    }
}
