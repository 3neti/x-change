<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Publication;

use InvalidArgumentException;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;

final readonly class PublicationDefinitionData
{
    /**
     * @param  list<string>  $verificationPaths
     */
    public function __construct(
        public string $id,
        public string $owner,
        public PublicationScope $scope,
        public PublicationInvocation $invocation,
        public string $target,
        public PublicationOverwritePolicy $overwritePolicy,
        public string $description,
        public bool $required = true,
        public bool $available = true,
        public bool $generated = false,
        public array $verificationPaths = [],
    ) {
        if (trim($this->id) === '' || trim($this->owner) === '' || trim($this->target) === '') {
            throw new InvalidArgumentException('Publication ID, owner, and target must not be empty.');
        }

        if ($this->scope === PublicationScope::Build && ! $this->generated) {
            throw new InvalidArgumentException("Build publication [{$this->id}] must be package-owned generated output.");
        }

        if ($this->scope === PublicationScope::Build && $this->overwritePolicy !== PublicationOverwritePolicy::AlwaysGenerated) {
            throw new InvalidArgumentException("Build publication [{$this->id}] must use the always-generated overwrite policy.");
        }
    }

    public function invocationKey(): string
    {
        return implode(':', [$this->scope->value, $this->invocation->value, $this->target]);
    }
}
