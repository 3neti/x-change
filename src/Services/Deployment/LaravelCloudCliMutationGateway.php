<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Contracts\Deployment\CloudMutationGatewayContract;
use RuntimeException;

final readonly class LaravelCloudCliMutationGateway implements CloudMutationGatewayContract
{
    public function bootstrap(string $application, string $region, string $databasePreset): void
    {
        $this->run('ship', [
            "--name={$application}", "--region={$region}", '--database=postgres18',
            "--database-preset={$databasePreset}", '-n',
        ]);
    }

    public function createDatabase(string $environmentId, string $name, string $region, string $type): void
    {
        $cluster = $this->runJson('database-cluster:create', [
            "--name={$name}", "--region={$region}", "--type={$type}", '--json', '-n',
        ]);
        $clusterId = $this->requiredId($cluster, 'database cluster');
        $database = $this->runJson('database:create', [
            $clusterId, "--name={$name}", '--json', '-n',
        ]);

        $this->run('environment:update', [
            $environmentId, '--database-id='.$this->requiredId($database, 'database'), '--force', '-n',
        ]);
    }

    public function createCache(string $environmentId, string $name, string $region, string $type, string $size): void
    {
        $cache = $this->runJson('cache:create', [
            "--name={$name}", "--region={$region}", "--type={$type}", "--size={$size}",
            '--auto-upgrade-enabled=true', '--is-public=false', '--json', '-n',
        ]);

        $this->run('environment:update', [
            $environmentId, '--cache-id='.$this->requiredId($cache, 'cache'), '--force', '-n',
        ]);
    }

    public function createCompute(string $environmentId, string $name, string $size): string
    {
        $instance = $this->runJson('instance:create', [
            $environmentId, "--name={$name}", '--type=app', "--size={$size}",
            '--scaling-type=none', '--min-replicas=1', '--max-replicas=1',
            '--uses-scheduler=true', '--json', '-n',
        ]);

        return $this->requiredId($instance, 'compute instance');
    }

    public function configureEnvironment(string $environmentId, string $buildCommand, string $deployCommand): void
    {
        $this->run('environment:update', [
            $environmentId, "--build-command={$buildCommand}", "--deploy-command={$deployCommand}",
            '--force', '-n',
        ]);
    }

    public function createWorker(string $instanceId, string $queue, int $timeout): void
    {
        $this->run('background-process:create', [
            $instanceId, '--type=worker', '--connection=database', "--queue={$queue}",
            "--timeout={$timeout}", '--sleep=3', '--tries=3', '--processes=1', '--json', '-n',
        ]);
    }

    public function enableScheduler(string $instanceId): void
    {
        $this->run('instance:update', [
            $instanceId, '--uses-scheduler=true', '--force', '-n',
        ]);
    }

    /** @param list<string> $arguments */
    private function run(string $command, array $arguments): string
    {
        $help = Process::timeout(30)->run(['cloud', $command, '-h', '-n']);

        if (! $help->successful()) {
            throw new RuntimeException("Laravel Cloud CLI command [{$command}] is unavailable.");
        }

        $result = Process::timeout(900)->idleTimeout(120)->run(['cloud', $command, ...$arguments]);

        if (! $result->successful()) {
            throw new RuntimeException("Laravel Cloud mutation [{$command}] failed safely.");
        }

        return $result->output();
    }

    /** @param list<string> $arguments
     * @return array<string, mixed>
     */
    private function runJson(string $command, array $arguments): array
    {
        $payload = json_decode($this->run($command, $arguments), true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : [];
    }

    /** @param array<string, mixed> $payload */
    private function requiredId(array $payload, string $resource): string
    {
        $id = trim((string) ($payload['id'] ?? ''));

        if ($id === '') {
            throw new RuntimeException("Laravel Cloud did not return a {$resource} identifier.");
        }

        return $id;
    }
}
