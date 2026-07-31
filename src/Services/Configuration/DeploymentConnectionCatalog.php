<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;
use RuntimeException;

final readonly class DeploymentConnectionCatalog
{
    /**
     * @param  iterable<DeploymentConnectionContributor>  $contributors
     */
    public function __construct(private iterable $contributors) {}

    /**
     * @return array<string, ProviderConnectionTemplateData>
     */
    public function templates(): array
    {
        $templates = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->connectionTemplates() as $template) {
                if ($template->provider !== $contributor->providerCode()) {
                    throw new RuntimeException(
                        "Deployment connection [{$template->reference}] provider mismatch.",
                    );
                }

                if (isset($templates[$template->reference])) {
                    throw new RuntimeException(
                        "Duplicate deployment connection [{$template->reference}].",
                    );
                }

                $templates[$template->reference] = $template;
            }
        }

        ksort($templates);

        return $templates;
    }
}
