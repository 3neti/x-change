<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use JsonException;
use LBHurtado\XChange\Data\Commercial\CommercialOfferingManifestData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use Symfony\Component\Yaml\Yaml;

final class CommercialOfferingManifestCompiler
{
    public const Schema = '3neti.x-change.commercial-offering-manifest.v1';

    /**
     * @throws JsonException
     */
    public function compile(string $profile, CommercialOfferingData $offering): CommercialOfferingManifestData
    {
        $document = $this->canonicalize([
            'schema' => self::Schema,
            'profile' => $profile,
            'offering' => $offering->toArray(),
        ]);
        $canonicalJson = json_encode(
            $document,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return new CommercialOfferingManifestData(
            schema: self::Schema,
            profile: $profile,
            hash: hash('sha256', $canonicalJson),
            yaml: Yaml::dump($document, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
            offering: $offering,
        );
    }

    /**
     * @throws JsonException
     */
    public function parse(string $yaml): CommercialOfferingManifestData
    {
        $document = Yaml::parse($yaml);

        if (! is_array($document)
            || ($document['schema'] ?? null) !== self::Schema
            || ! is_string($document['profile'] ?? null)
            || ! is_array($document['offering'] ?? null)) {
            throw new \DomainException('Commercial Offering manifest is malformed or uses an unsupported schema.');
        }

        $offering = CommercialOfferingData::fromArray($document['offering']);
        $compiled = $this->compile($document['profile'], $offering);

        if ($compiled->offering->snapshotHash() !== $offering->snapshotHash()) {
            throw new \DomainException('Commercial Offering manifest snapshot is inconsistent.');
        }

        return $compiled;
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
