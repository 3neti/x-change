<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\XChange\Contracts\TreasuryVocabularyReadModelContract;
use Throwable;

final class AdvisoryTreasuryVocabularyReadModel implements TreasuryVocabularyReadModelContract
{
    private const JURISDICTION_CONTEXT_RESOLVER = 'LBHurtado\\XLegal\\Contracts\\JurisdictionContextResolverContract';

    private const LEGAL_VOCABULARY_RESOLVER = 'LBHurtado\\XLegal\\Contracts\\LegalVocabularyResolverContract';

    public function terms(array $keys): array
    {
        $terms = [];

        foreach (array_values(array_unique($keys)) as $key) {
            $terms[$key] = $this->legalTerm($key) ?? $this->fallbackTerm($key);
        }

        return $terms;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     source: string,
     *     profile: string,
     *     profile_version: string,
     *     approved_for_public_display: bool
     * }|null
     */
    private function legalTerm(string $key): ?array
    {
        if (
            ! app()->bound(self::JURISDICTION_CONTEXT_RESOLVER)
            || ! app()->bound(self::LEGAL_VOCABULARY_RESOLVER)
        ) {
            return null;
        }

        try {
            $context = app(self::JURISDICTION_CONTEXT_RESOLVER)->resolve([
                'legal_entity_reference' => config('x-change.treasury.legal_entity_reference'),
                'profile' => config('x-change.treasury.legal_profile'),
            ]);
            $term = app(self::LEGAL_VOCABULARY_RESOLVER)->resolve($key, $context, 'operator');

            return [
                'key' => (string) $term->key,
                'label' => (string) $term->label,
                'description' => (string) $term->description,
                'source' => 'x-legal',
                'profile' => (string) $term->profile,
                'profile_version' => (string) $term->profileVersion,
                'approved_for_public_display' => (bool) $term->approvedForPublicDisplay,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     source: string,
     *     profile: string,
     *     profile_version: string,
     *     approved_for_public_display: bool
     * }
     */
    private function fallbackTerm(string $key): array
    {
        /** @var array{label?: mixed, description?: mixed} $definition */
        $definition = (array) config("x-change.treasury.vocabulary.{$key}", []);
        $label = trim((string) ($definition['label'] ?? $key));
        $description = trim((string) ($definition['description'] ?? ''));

        return [
            'key' => $key,
            'label' => $label !== '' ? $label : $key,
            'description' => $description,
            'source' => 'x-change',
            'profile' => (string) config('x-change.treasury.legal_profile'),
            'profile_version' => (string) config('x-change.treasury.legal_profile_version'),
            'approved_for_public_display' => false,
        ];
    }
}
