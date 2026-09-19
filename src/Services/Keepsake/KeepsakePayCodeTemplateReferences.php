<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Models\PayCodeTemplate;

final class KeepsakePayCodeTemplateReferences
{
    /** @return array<string, string> */
    public function forContext(InstanceKeepsakeContext $context): array
    {
        $ownerKeys = [];

        foreach ($context->users as $user) {
            $ownerKeys[$user['model']->getMorphClass()][] = $user['model']->getKey();
        }

        if ($ownerKeys === []) {
            return [];
        }

        $query = PayCodeTemplate::query()
            ->select(['id', 'owner_type', 'owner_id'])
            ->where('status', 'active')
            ->where(function ($query) use ($ownerKeys): void {
                foreach ($ownerKeys as $ownerType => $ownerIds) {
                    $query->orWhere(function ($owner) use ($ownerType, $ownerIds): void {
                        $owner->where('owner_type', $ownerType)->whereIn('owner_id', $ownerIds);
                    });
                }
            })
            ->orderBy('id');
        $references = [];

        foreach ($query->cursor() as $template) {
            $references[(string) $template->getKey()] = 'template-'.str_pad(
                (string) (count($references) + 1),
                6,
                '0',
                STR_PAD_LEFT,
            );
        }

        return $references;
    }
}
