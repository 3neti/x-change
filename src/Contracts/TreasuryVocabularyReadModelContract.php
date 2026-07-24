<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface TreasuryVocabularyReadModelContract
{
    /**
     * @param  list<string>  $keys
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     source: string,
     *     profile: string,
     *     profile_version: string,
     *     approved_for_public_display: bool
     * }>
     */
    public function terms(array $keys): array;
}
