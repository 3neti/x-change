<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use JsonException;
use LBHurtado\XChange\Data\Commercial\CommercialComponentEconomicsManifestData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use Symfony\Component\Yaml\Yaml;

final class CommercialComponentEconomicsManifestCompiler
{
    public const Schema = '3neti.x-change.commercial-component-economics-manifest.v1';

    /** @throws JsonException */
    public function compile(
        string $profile,
        CommercialOfferingData $offering,
        string $offeringManifestHash,
        CommercialComponentEconomicsSetData $componentEconomics,
    ): CommercialComponentEconomicsManifestData {
        if (preg_match('/^[a-f0-9]{64}$/', $offeringManifestHash) !== 1) {
            throw new \DomainException('Commercial Offering manifest hash must be an authoritative SHA-256 value.');
        }

        $componentEconomics->assertMatchesCatalog($offering->catalog);
        $document = $this->canonicalize([
            'schema' => self::Schema,
            'profile' => $profile,
            'offering' => [
                'reference' => $offering->reference,
                'version' => $offering->version,
                'snapshot_hash' => $offering->snapshotHash(),
                'manifest_hash' => $offeringManifestHash,
            ],
            'component_economics' => $componentEconomics->toArray(),
        ]);
        $canonicalJson = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new CommercialComponentEconomicsManifestData(
            schema: self::Schema,
            profile: $profile,
            offeringReference: $offering->reference,
            offeringVersion: $offering->version,
            offeringSnapshotHash: $offering->snapshotHash(),
            offeringManifestHash: $offeringManifestHash,
            hash: hash('sha256', $canonicalJson),
            yaml: Yaml::dump($document, 16, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
            componentEconomics: $componentEconomics,
        );
    }

    /** @throws JsonException */
    public function parse(
        string $yaml,
        CommercialOfferingData $offering,
        string $offeringManifestHash,
    ): CommercialComponentEconomicsManifestData {
        $document = Yaml::parse($yaml);

        if (! is_array($document)
            || ($document['schema'] ?? null) !== self::Schema
            || ! is_string($document['profile'] ?? null)
            || ! is_array($document['offering'] ?? null)
            || ! is_array($document['component_economics'] ?? null)) {
            throw new \DomainException('Commercial Component Economics manifest is malformed or uses an unsupported schema.');
        }

        $identity = $document['offering'];
        if (($identity['reference'] ?? null) !== $offering->reference
            || (int) ($identity['version'] ?? 0) !== $offering->version
            || ($identity['snapshot_hash'] ?? null) !== $offering->snapshotHash()
            || ($identity['manifest_hash'] ?? null) !== $offeringManifestHash) {
            throw new \DomainException('Commercial Component Economics manifest does not match the referenced Commercial Offering.');
        }

        return $this->compile(
            profile: $document['profile'],
            offering: $offering,
            offeringManifestHash: $offeringManifestHash,
            componentEconomics: CommercialComponentEconomicsSetData::fromArray($document['component_economics']),
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
