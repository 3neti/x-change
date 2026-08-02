<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Publication;

use InvalidArgumentException;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationScope;

final readonly class PublicationCatalog
{
    /** @param iterable<XChangePublicationContributor> $contributors */
    public function __construct(private iterable $contributors) {}

    /** @return list<PublicationDefinitionData> */
    public function definitions(?PublicationScope $scope = null): array
    {
        $definitions = [];
        $invocations = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->publications() as $definition) {
                if (! $definition instanceof PublicationDefinitionData) {
                    throw new InvalidArgumentException(sprintf(
                        'Publication contributor [%s] returned an invalid definition.',
                        $contributor::class,
                    ));
                }

                if (isset($definitions[$definition->id])) {
                    throw new InvalidArgumentException("Duplicate publication ID [{$definition->id}].");
                }

                $invocationKey = $definition->invocationKey();

                if (isset($invocations[$invocationKey])) {
                    throw new InvalidArgumentException(
                        "Conflicting publication invocation [{$invocationKey}] for [{$definition->id}] and [{$invocations[$invocationKey]}].",
                    );
                }

                $definitions[$definition->id] = $definition;
                $invocations[$invocationKey] = $definition->id;
            }
        }

        ksort($definitions);

        return array_values(array_filter(
            $definitions,
            static fn (PublicationDefinitionData $definition): bool => $scope === null || $definition->scope === $scope,
        ));
    }
}
