<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

interface LifecycleOutputContract
{
    public function line(string $message): void;

    public function info(string $message): void;

    public function warn(string $message): void;

    public function error(string $message): void;

    public function isJson(): bool;

    public function acceptPending(): bool;
}
