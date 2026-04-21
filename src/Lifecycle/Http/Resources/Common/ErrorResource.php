<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Common;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => false,
            'message' => (string) data_get($this->resource, 'message'),
            'code' => (string) data_get($this->resource, 'code'),
            'errors' => (array) data_get($this->resource, 'errors', []),
        ];
    }
}
