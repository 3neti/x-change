<?php

namespace LBHurtado\ModelInput\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use LBHurtado\ModelInput\Enums\Input;

interface InputsInterface
{
    public function inputs(): MorphMany;
    public function setInput(string|Input $name, string $value): self;
    public function forceSetInput(string|Input $name, string $value): self;
    public function isValidInput(string|Input $name, ?string $value = null): bool;
}
