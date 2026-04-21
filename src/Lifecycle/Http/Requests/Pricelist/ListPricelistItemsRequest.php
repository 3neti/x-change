<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Pricelist;

use Illuminate\Foundation\Http\FormRequest;

class ListPricelistItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
