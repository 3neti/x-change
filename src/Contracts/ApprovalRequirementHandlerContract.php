<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ApprovalRequirementHandlerContract
{
    public function requirement(): string;

    public function handle(array $meta = [], array $context = []): array;
}
