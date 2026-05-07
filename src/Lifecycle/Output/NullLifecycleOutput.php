<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Output;

final class NullLifecycleOutput implements LifecycleOutputContract
{
    public function line(string $message): void {}
    public function info(string $message): void {}
    public function warn(string $message): void {}
    public function error(string $message): void {}

    public function newLine(): void {}

    public function isJson(): bool
    {
        return true; // API is always JSON mode
    }

    public function acceptPending(): bool
    {
        return true;
    }
}
