<?php

namespace LBHurtado\ModelInput\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LBHurtado\ModelInput\Enums\InputType;
interface InputInterface
{
    public function inputs(): MorphMany;
    public function setInput(string|InputType $name, string $value): self;
    public function forceSetInput(string|InputType $name, string $value): self;
    public function isValidInput(string|InputType $name, ?string $value = null): bool;
}
