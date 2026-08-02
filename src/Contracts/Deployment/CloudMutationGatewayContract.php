<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Deployment;

interface CloudMutationGatewayContract
{
    public function bootstrap(string $application, string $region, string $databasePreset): void;

    public function createDatabase(string $environmentId, string $name, string $region, string $type): void;

    public function createCache(string $environmentId, string $name, string $region, string $type, string $size): void;

    public function createCompute(string $environmentId, string $name, string $size): string;

    public function configureEnvironment(string $environmentId, string $buildCommand, string $deployCommand): void;

    public function createWorker(string $instanceId, string $queue, int $timeout): void;

    public function enableScheduler(string $instanceId): void;
}
