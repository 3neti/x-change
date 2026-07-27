<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Models\PayCodeTemplate;

final class PayCodeTemplateReadModel
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function for(?Authenticatable $owner): array
    {
        if (
            ! $owner instanceof Model
            || ! Schema::hasTable((new PayCodeTemplate)->getTable())
        ) {
            return [];
        }

        return PayCodeTemplate::query()
            ->whereMorphedTo('owner', $owner)
            ->where('status', 'active')
            ->latest('updated_at')
            ->get()
            ->map(fn (PayCodeTemplate $template): array => [
                'reference' => $template->reference,
                'name' => $template->name,
                'description' => $template->description,
                'base_template_key' => $template->base_template_key,
                'instructions' => $template->instructions_ciphertext,
                'include_amount' => $template->include_amount,
                'include_purpose' => $template->include_purpose,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ])
            ->all();
    }
}
